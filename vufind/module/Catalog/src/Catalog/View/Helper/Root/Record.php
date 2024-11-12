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

use Catalog\Utils\RegexLookup as Regex;
use Laminas\Config\Config;
use VuFind\Config\YamlReader;
use VuFind\ILS\Logic\AvailabilityStatus;
use VuFind\ILS\Logic\AvailabilityStatusInterface;
use VuFind\Tags\TagsService;

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
     * Constructor
     *
     * @param TagsService            $tagsService    Tags service
     * @param Config                 $config         Configuration from config.ini
     * @param \Laminas\Config\Config $browzineConfig config object for the BrowZine.ini file // MSU
     */
    public function __construct(
        protected TagsService $tagsService,
        protected ?Config $config = null,
        $browzineConfig = null // MSU
    ) {
        parent::__construct($tagsService, $config);
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

        if (!array_key_exists('desc', $link)) {
            $link['desc'] = '';
        }

        // Add prefix to bookplate URLs
        if (isset($link['url']) && str_contains($link['url'], 'bookplate')) {
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
            if (isset($mat['desc'])) {
                $found &= preg_match($mat['desc'], $link['desc']);
            }
            if (isset($mat['url'])) {
                $found &= preg_match($mat['url'], $link['url'] ?? '');
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
     * @param array $holding   the holding data
     * @param bool  $translate if the transEsc function should
     *              be used on the status values
     *
     * @return string
     */
    public function getStatus(&$holding, $translate = true)
    {
        // NOTE: Make sure this logic matches with getStatus in the GetThisLoader

        if ($translate === true) {
            $transEsc = $this->getView()->plugin('transEsc');
        }

        $status = isset($holding['availability']) ? $holding['availability']->getStatusDescription() : 'Unknown';
        $statusSecondPart = '';
        if (Regex::SPEC_COLL($holding['location'] ?? '')) {
            $statusFirstPart = 'Unavailable';
            $availability = false;
        } elseif (
            in_array($status, [
                'Aged to lost', 'Claimed returned', 'Declared lost', 'In process',
                'In process (non-requestable)', 'Long missing', 'Lost and paid', 'Missing', 'On order', 'Order closed',
                'Unknown', 'Withdrawn',
            ])
        ) {
            $statusFirstPart = 'Unavailable';
            $statusSecondPart = $status;
            $availability = false;
        } elseif (in_array($status, ['Awaiting pickup', 'Awaiting delivery', 'In transit', 'Paged'])) {
            $statusFirstPart = 'Checked Out';
            $statusSecondPart = $status;
            $availability = false;
        } elseif ($status === 'Checked out') {
            $statusFirstPart = 'Checked Out';
            $availability = false;
        } elseif ($status === 'Restricted') {
            $statusFirstPart = 'Library Use Only';
            $availability = AvailabilityStatusInterface::STATUS_UNCERTAIN;
        } elseif (!in_array($status, ['Available', 'Unavailable', 'Checked out'])) {
            $statusFirstPart = 'Unknown status';
            $statusSecondPart = $status;
            $availability = AvailabilityStatusInterface::STATUS_UNKNOWN;
        } elseif (isset($holding['reserve']) && $holding['reserve'] === 'Y') {
            $statusFirstPart = 'On Reserve';
            $availability = true;
        } elseif ($status === 'Available') {
            $statusFirstPart = 'Available';
            $availability = true;
        } else {
            $statusFirstPart = 'Unknown status';
            $availability = AvailabilityStatusInterface::STATUS_UNKNOWN;
        }

        $status = isset($transEsc) ? $transEsc($statusFirstPart) : $statusFirstPart;
        if (!empty($statusSecondPart)) {
            $status .= ' (' . (isset($transEsc) ? $transEsc($statusSecondPart) : $statusSecondPart) . ')';
        }
        $status .= $this->getStatusSuffix($holding, $translate);
        $holding['availability'] = new AvailabilityStatus(
            $availability,
            $status
        );

        return $status;
    }

    /**
     * Determine the holding status suffix (if any)
     *
     * @param array $holding   the holding data
     * @param bool  $translate if the transEsc function should
     *              be used on the status values
     *
     * @return string
     */
    public function getStatusSuffix($holding, $translate = true)
    {
        if ($translate === true) {
            $transEsc = $this->getView()->plugin('transEsc');
        }
        $suffix = '';
        if ($holding['returnDate'] ?? false) {
            $suffix = ' - ' . $holding['returnDate'];
        }
        if ($holding['duedate'] ?? false) {
            $due = isset($transEsc) ? $transEsc('Due') : 'Due';
            $suffix .= ' - ' . $due . ': ' . $holding['duedate'];
        }
        if ($holding['temporary_loan_type'] ?? false) {
            $suffix .= ' (' . $holding['temporary_loan_type'] . ')';
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
     * @return string|array  JSON data returned from LibKey within the 'data' element
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
     * @return void No data returned from this, only sets the configs in class vars
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
            // TODO After upgrade to version containing PR with SecretTrait, switch to it
            if (
                isset($this->browzineConfig->General->access_token_file)
                && $content = file_get_contents($this->browzineConfig->General->access_token_file)
            ) {
                $this->libkeyAccessToken = $content;
            } elseif (isset($this->browzineConfig->General->access_token)) {
                $this->libkeyAccessToken = $this->browzineConfig->General->access_token;
            }
            if (isset($this->browzineConfig->General->library_id)) {
                $this->libkeyLibraryId = $this->browzineConfig->General->library_id;
            }
        }
    }
}
