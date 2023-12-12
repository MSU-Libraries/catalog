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
class Record extends \VuFind\View\Helper\Root\Record implements \Laminas\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;

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
     * Config for the BrowZine file
     *
     * @var \Catalog\View\Helper\Root\Record
     */
    private $browzineConfig;

    /**
     * Library ID used for LibKey queries
     *
     * @var string
     */
    private $libkeyLibraryId;

    /**
     * Access token used for LibKey queries
     *
     * @var string
     */
    private $libkeyAccessToken;

    /**
     * Initialize the record driver
     *
     * @param string                 $config         Name of the config to load
     * @param \Laminas\Config\Config $browzineConfig config object for the BrowZine.ini file
     */
    public function __construct($config = null, $browzineConfig = null)
    {
        parent::__construct($config);
        $yamlReader = new YamlReader();
        $this->accessLinksConfig = $yamlReader->get('accesslinks.yaml');
        if (array_key_exists('labels', $this->accessLinksConfig)) {
            $this->linkLabels = $this->accessLinksConfig['labels'];
        }
        $this->browzineConfig = $browzineConfig;
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

        if (!array_key_exists('desc', $link)) {
            $link['desc'] = '';
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
        if ($label !== null) {
            $link['desc'] .= " ({$label})";
        } elseif ($link['desc'] == null || trim($link['desc']) == '') {
            // In case there is still no description by this point,
            // add one so there isn't a blank link on the page
            $link['desc'] = 'Access Content Online';
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
     * Get the fields required for the templates from LibKey
     *
     * @param string $doi the DOI to validate with LibKey and build the URL with
     *
     * @return array keys: pdf (string), article (string), issue (string), openAccess (bool)
     */
    public function getLibKeyData($doi)
    {
        $parsedData = ['pdf' => '', 'article' => '', 'issue' => '', 'openAccess' => false];

        // get JSON data for the DOI
        $data = $this->getLibKeyJSON($doi);

        if (empty($data)) {
            return $parsedData;
        }

        // check the JSON for the required urls
        if (array_key_exists('fullTextFile', $data)) {
            $parsedData['pdf'] = $data['fullTextFile'];
        }
        if (array_key_exists('browzineWebLink', $data)) {
            $parsedData['issue'] = $data['browzineWebLink'];
        }
        if (array_key_exists('contentLocation', $data)) {
            $parsedData['article'] = $data['contentLocation'];
        }
        if (array_key_exists('openAccess', $data)) {
            $parsedData['openAccess'] = $data['openAccess'];
        }

        return $parsedData;
    }

    /**
     * Get the JSON response from the LibKey API.
     *
     * @param string $doi the DOI to validate with LibKey and build the URL with
     *
     * @return string  JSON data returned from LibKey within the 'data' element
     */
    private function getLibKeyJSON($doi)
    {
        $data = '';

        // check if we have credentials, if not, return now
        $this->loadLibKeyConfigs();
        if (empty($this->libkeyAccessToken) || empty($this->libkeyLibraryId) || empty($doi)) {
            return $data;
        }

        $url = 'https://public-api.thirdiron.com/public/v1/libraries/' .
              $this->libkeyLibraryId . '/articles/doi/' . $doi .
              '?access_token=' . $this->libkeyAccessToken;
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_HTTPGET, 1);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_TIMEOUT, 8);
        $output = curl_exec($curl);

        // if there are no errors, attempt to decode the json response
        if ($e = curl_error($curl)) {
            $this->logError('Error occurred when querying LibKey API for DOI ' .
                            $doi . '. ' . $e);
        } else {
            // if there was no data from the API, then  they don't have that DOI in the LibKey system
            if (empty($output)) {
                return $data;
            }
            $data = json_decode($output, true);
            if ($data === null) {
                $this->logError('Error occured parsing the JSON data from LibKey for DOI ' .
                                $doi . '. Error: ' . json_last_error_msg() . '. Full Response: ' . $output);
                $data = '';
            } else {
                // remove the layer of nesting to make parsing later more straight forward
                if (array_key_exists('data', $data)) {
                    $data = $data['data'];
                }
            }
        }
        curl_close($curl);

        return $data;
    }

    /**
     * Load the LibKey configurations from the BrowZine.ini file
     *
     * @return Null No data returned from this, only sets the configs in class vars
     */
    private function loadLibKeyConfigs()
    {
        // check if already have them loaded, return now
        if (!empty($this->libkeyAccessToken) && !empty($this->libkeyLibraryId)) {
            return;
        }

        $this->libkeyAccessToken = '';
        $this->libkeyLibraryId = '';

        // parse the access_token and library_id fields
        if (isset($this->browzineConfig->General)) {
            if (isset($this->browzineConfig->General->access_token)) {
                $this->libkeyAccessToken = $this->browzineConfig->General->access_token;
            }
            if (isset($this->browzineConfig->General->library_id)) {
                $this->libkeyLibraryId = $this->browzineConfig->General->library_id;
            }
        }
    }
}
