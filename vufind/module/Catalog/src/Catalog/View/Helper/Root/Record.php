<?php

/**
 * Default values for the record
 *
 * PHP version 7
 *
 * @category VuFind
 * @package  View_Helper
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */

namespace Catalog\View\Helper\Root;

use VuFind\Config\YamlReader;

use function array_key_exists;
use function in_array;

/**
 * Extend the Record data available to the View
 *
 * @category VuFind
 * @package  View_Helper
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
class Record extends \VuFind\View\Helper\Root\Record
{
    /**
     * Link labels loaded from 'labels' key in accesslinks.yaml config. Each entry:
     *   label: The string to add to the link desc
     *   desc : Regex must match against the 'desc' field for label to match; or null to ignore
     *   url  : Regex must match against the 'url' field for label to match; or null to ignore
     */
    private $linkLabels = [];

    /**
     * Config for the access links
     *
     * @var \Catalog\View\Helper\Root\Record
     */
    private $accessLinksConfig;

    /**
     * Initialize the record driver
     *
     * @param string $config Name of the config to load
     */
    public function __construct($config = null)
    {
        parent::__construct($config);
        $yamlReader = new YamlReader();
        $this->accessLinksConfig = $yamlReader->get('accesslinks.yaml');
        if (array_key_exists('labels', $this->accessLinksConfig)) {
            $this->linkLabels = $this->accessLinksConfig['labels'];
        }
    }

    /**
     * Given a link array, update the 'desc' to add an idenitfer
     * for the platform the link points to.
     *
     * @param array $link An array with 'url' and 'desc' keys
     *
     * @return array
     */
    public function getLinkTargetLabel($link)
    {
        $label = null;

        // Add prefix to bookplate URLs
        if (str_contains($link['url'], 'bookplate')) {
            $link['desc'] = 'Book Plate: ' . $link['desc'];
        }

        // Add labels to links
        foreach ($this->linkLabels as $mat) {
            // Skip entries missing the 'label' field
            if (!array_key_exists('label', $mat)) {
                continue;
            }
            // Must have one of the regex patterns, otherwise false
            $found = ($mat['desc'] ?? null) || ($mat['url'] ?? null);
            if ($mat['desc'] ?? null) {
                $found &= preg_match($mat['desc'], $link['desc']);
            }
            if ($mat['url'] ?? null) {
                $found &= preg_match($mat['url'], $link['url']);
            }
            if ($found) {
                $label = $mat['label'];
                break;
            }
        }
        if ($label !== null && array_key_exists('desc', $link)) {
            $link['desc'] .= " ({$label})";
        }
        return $link;
    }

    /**
     * Get all the links associated with this record.  Returns an array of
     * associative arrays each containing 'desc' and 'url' keys.
     *
     * @param bool $openUrlActive Is there an active OpenURL on the page?
     *
     * @return array
     */
    public function getLinkDetails($openUrlActive = false)
    {
        $links = $this->driver->tryMethod('geteJournalLinks') ?? [];
        foreach ($links as $idx => $link) {
            if (strcasecmp($link['desc'] ?? '', 'cover image') === 0) {
                unset($links[$idx]);
                break;
            }
            if (str_contains($link['url'] ?? '', 'bookplate')) {
                unset($links[$idx]);
                break;
            }
        }
        return $this->deduplicateLinks(
            array_map([$this,'getLinkTargetLabel'], $links)
        );
    }

    /**
     * Generate a thumbnail URL (return false if unsupported).
     * Overriding getThumbnail to use link with label "Cover image" when available
     *
     * @param string $size Size of thumbnail (small, medium or large -- small is
     * default).
     *
     * @return string|bool
     */
    public function getThumbnail($size = 'small')
    {
        $url = false;
        // Check if a link is provided with "Cover image" as the label
        $links = parent::getLinkDetails();
        foreach ($links as $link) {
            if (strcasecmp($link['desc'], 'cover image') === 0) {
                $url = $link['url'];
            }
        }
        // When no "Cover image" link is available, fall pack to default
        if (!$url) {
            $url = parent::getThumbnail($size);
        }
        return $url;
    }

    /**
     * Determine the holding status
     *
     * @param array $holding the holding data
     *
     * @return string
     */
    public function getStatus($holding)
    {
        $transEsc = $this->getView()->plugin('transEsc');
        $status = $holding['status'];
        if (
            in_array($status, ['Aged to lost', 'Claimed returned', 'Declared lost', 'In process',
            'In process (non-requestable)', 'Long missing', 'Lost and paid', 'Missing', 'On order', 'Order closed',
            'Unknown', 'Withdrawn'])
        ) {
            $status = $transEsc('Unavailable') . ' (' . $transEsc($status) . ')';
        } elseif (in_array($status, ['Awaiting pickup', 'Awaiting delivery', 'In transit', 'Paged'])) {
            $status = $transEsc('Checked Out') . ' (' . $transEsc($status) . ')';
        } elseif ($status == 'Restricted') {
            $status = $transEsc('Library Use Only');
        } elseif (!in_array($status, ['Available', 'Unavailable', 'Checked out'])) {
            $status = $transEsc('Unknown status') . ' (' . $transEsc($status) . ')';
        } elseif ($holding['reserve'] === 'Y') {
            $status = 'On Reserve';
        }
        return $status;
    }

    /**
     * Determine the holding status suffix (if any)
     *
     * @param array $holding the holding data
     *
     * @return string
     */
    public function getStatusSuffix($holding)
    {
        $transEsc = $this->getView()->plugin('transEsc');
        $suffix = '';
        if ($holding['returnDate'] ?? false) {
            $suffix = ' - ' . $holding['returnDate'];
        }
        if ($holding['duedate'] ?? false) {
            $suffix = $suffix . ' - ' . $transEsc('Due') . ':' . $holding['duedate'];
        }
        return $suffix;
    }

    /**
     * Generate a URL to the LibKey system if a 200 response would be returned for
     * the item (based on a query to another URL)
     *
     * @param string $doi the DOI to validate with LibKey and build the URL with
     *
     * @return string|bool
     */
    public function getLibKeyUrl($doi)
    {
        $url = false;

        if ($doi) {
            $checkUrl = 'https://public-api.thirdiron.com/public/v1/libraries/' .
                  getenv('BROWZINE_LIBRARY') . '/articles/doi/' . $doi .
                  '?access_token=' . getenv('BROWZINE_TOKEN');
            $ch = curl_init($checkUrl);
            curl_setopt($ch, CURLOPT_HTTPGET, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_HEADER, 1);    // we want headers
            curl_setopt($ch, CURLOPT_NOBODY, 0);    // we don't need body
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            $output = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpcode == 200) {
                $url = 'https://libkey.io/libraries/' . getenv('BROWZINE_LIBRARY') . '/' . $doi;
            }
        }
        return $url;
    }
}
