<?php

namespace Catalog\View\Helper\Root;

class Record extends \VuFind\View\Helper\Root\Record
{
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
        $details = parent::getLinkDetails($openUrlActive);

        return $this->deduplicateLinks($details);
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
