<?php

namespace Catalog\View\Helper\Root;

class Record extends \VuFind\View\Helper\Root\Record
{
    /**
     * Added labels to links:
     *   label: The string to add to the link desc
     *   desc : Regex must match against the 'desc' field for label to match; or null to ignore
     *   url  : Regex must match against the 'url' field for label to match; or null to ignore
     */
    private $linkLabels = array(
        [ 'label' => 'EBSCO',
            'desc' => null,
            'url' => '#https://search\\.ebscohost\\.com/#' ],
        [ 'label' => 'Alexander Street',
            'desc' => null,
            'url' => '#https://www\\.aspresolver\\.com/#' ],
        [ 'label' => 'Ebook Central @ Proquest',
            'desc' => null,
            'url' => '#https://ebookcentral\\.proquest\\.com/lib/michstate-ebooks#' ],
        [ 'label' => 'Hein Online',
            'desc' => null,
            'url' => '#https://heinonline\\.org/#' ],
        [ 'label' => 'Springer Link',
            'desc' => null,
            'url' => '#https://link\\.springer\\.com/#' ],
        [ 'label' => 'University of North Texas Ditigal Library',
            'desc' => null,
            'url' => '#https://digital\\.library\\.unt\\.edu/#' ],
        [ 'label' => 'National Center for Biotechnology Information @ National Library of Medicine',
            'desc' => null,
            'url' => '#https://www\\.ncbi\\.nlm\\.nih\\.gov/#' ],
        [ 'label' => 'Digital Object Identifier Permalink',
            'desc' => null,
            'url' => '#https://(dx\.)?doi\\.org/#' ],
        [ 'label' => 'FRASER @ Federal Reserve Bank of St. Louis',
            'desc' => null,
            'url' => '#https://fraser\\.stlouisfed\\.org/#' ],
        [ 'label' => 'ScienceDirect',
            'desc' => null,
            'url' => '#https://www\\.sciencedirect\\.com/#' ],
        [ 'label' => 'Brill',
            'desc' => null,
            'url' => '#https://brill\\.com/#' ],
        [ 'label' => 'Firenze University Press',
            'desc' => null,
            'url' => '#https://www\\.fupress\\.com/#' ],
        [ 'label' => 'Handle.Net Permalink',
            'desc' => null,
            'url' => '#https://hdl\\.handle\\.net/#' ],
        [ 'label' => 'Wiley Online Library',
            'desc' => null,
            'url' => '#https://onlinelibrary\\.wiley\\.com/#' ],
        [ 'label' => 'University of California Press E-Books Collection',
            'desc' => null,
            'url' => '#https://publishing\\.cdlib\\.org/ucpressebooks/#' ],
        [ 'label' => 'Open Access Publishing in European Networks',
            'desc' => null,
            'url' => '#https://library\\.oapen\\.org/#' ],
        [ 'label' => 'Society for Industrial and Applied Mathematics',
            'desc' => null,
            'url' => '#https://epubs\\.siam\\.org/#' ],
        [ 'label' => 'Taylor & Francis eBooks',
            'desc' => null,
            'url' => '#https://www\\.taylorfrancis\\.com/#' ],
        [ 'label' => 'IEEE Xplore',
            'desc' => null,
            'url' => '#https://ieeexplore\\.ieee\\.org/#' ],
        [ 'label' => 'JSTOR',
            'desc' => null,
            'url' => '#https://www\\.jstor\\.org/#' ],
        [ 'label' => 'Frontiers',
            'desc' => null,
            'url' => '#https://journal\\.frontiersin\\.org/#' ],
        [ 'label' => 'International Monetary Fund eLibrary',
            'desc' => null,
            'url' => '#https://www\\.elibrary\\.imf\\.org/#' ],
        [ 'label' => 'Adam Matthew Digital',
            'desc' => null,
            'url' => '#http://([a-z0-9.-]*)?amdigital\\.co\\.uk/#' ],
        [ 'label' => 'World Bank eLibrary',
            'desc' => null,
            'url' => '#https://elibrary\\.worldbank\\.org/#' ],
        [ 'label' => 'ClinicalKey',
            'desc' => null,
            'url' => '#https://www\\.clinicalkey\\.com/#' ],
        [ 'label' => 'American Chemical Society Publications',
            'desc' => null,
            'url' => '#https://pubs\\.acs\\.org/#' ],
        [ 'label' => 'R2 Digital Library',
            'desc' => null,
            'url' => '#https://www\\.r2library\\.com/#' ],
        [ 'label' => 'Open Book Publishers',
            'desc' => null,
            'url' => '#https://www\\.openbookpublishers\\.com/#' ],
        [ 'label' => 'Open Textbook Library @ University of Minnesota',
            'desc' => null,
            'url' => '#https://open.umn.edu/opentextbooks/#' ],
        [ 'label' => 'Directory of Open Access Books',
            'desc' => null,
            'url' => '#https://directory\\.doabooks\\.org/#' ],
    );

    /**
     * Given a link array, update the 'desc' to add an idenitfer
     * for the platform the link points to.
     *
     * @param array $link An array with 'url' and 'desc' keys
     *
     * @return array
     */
    public function getLinkTargetLabel($link) {
        $label = null;
        foreach ($this->linkLabels as $mat) {
            # Must have one of the regex patterns, otherwise false
            $found = ($mat['desc'] !== null || $mat['url'] !== null);
            if ($mat['desc'] !== null) {
                $found &= preg_match($mat['desc'], $link['desc']);
            }
            if ($mat['url'] !== null) {
                $found &= preg_match($mat['url'], $link['url']);
            }
            if ($found) {
                $label = $mat['label'];
                break;
            }
        }
        if ($label !== null) { $link['desc'] .= " ({$label})"; }
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
        $links = parent::getLinkDetails($openUrlActive);
        $links = $this->deduplicateLinks($links);
        return array_map([$this,'getLinkTargetLabel'], $links);
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
}
