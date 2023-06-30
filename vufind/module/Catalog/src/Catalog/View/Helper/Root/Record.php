<?php
namespace Catalog\View\Helper\Root;

use VuFind\Config\YamlReader;

class Record extends \VuFind\View\Helper\Root\Record
{
    /**
     * Link labels loaded from 'labels' key in accesslinks.yaml config. Each entry:
     *   label: The string to add to the link desc
     *   desc : Regex must match against the 'desc' field for label to match; or null to ignore
     *   url  : Regex must match against the 'url' field for label to match; or null to ignore
     */
    private $linkLabels = array();

    function __construct($config = null) {
        parent::__construct($config);
        $yamlReader = new YamlReader();
        $this->accessLinksConfig = $yamlReader->get("accesslinks.yaml");
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
        foreach ($this->linkLabels as $mat) {
            # Skip entries missing the 'label' field
            if (!array_key_exists('label', $mat)) {
                continue;
            }
            # Must have one of the regex patterns, otherwise false
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
            if (strcasecmp($link['desc'] ?? "", "cover image") === 0) {
                unset($links[$idx]);
                break;
            }
        }
        return $this->deduplicateLinks(
            array_map([$this,'getLinkTargetLabel'], $links)
        );
    }

    /**
     * Remove duplicates from the array. All keys and values are being used
     * recursively to compare, so if there are 2 links with the same url
     * but different desc, they will both be preserved.
     *
     * @param array $links array of associative arrays,
     * each containing 'desc' and 'url' keys
     *
     * @return array
     */
    protected function deduplicateLinks($links)
    {
        return array_values(array_unique($links, SORT_REGULAR));
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
            if (strcasecmp($link['desc'], "cover image") === 0) {
                $url = $link['url'];
            }
        }
        // When no "Cover image" link is available, fall pack to default
        if (!$url) {
            $url = parent::getThumbnail($size);
        }
        return $url;
    }

}
