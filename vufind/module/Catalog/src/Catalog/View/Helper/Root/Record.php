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
        // See if there are any links available:
        $urls = $this->driver->tryMethod('getURLs');
        if (empty($urls) || ($openUrlActive && $this->hasOpenUrlReplaceSetting())) {
            return [];
        }

        // If we found links, we may need to convert from the "route" format
        // to the "full URL" format.
        $urlHelper = $this->getView()->plugin('url');
        $serverUrlHelper = $this->getView()->plugin('serverurl');
        $formatLink = function ($link) use ($urlHelper, $serverUrlHelper) {
            // Error if route AND URL are missing at this point!
            if (!isset($link['route']) && !isset($link['url'])) {
                throw new \Exception('Invalid URL array.');
            }

            // Build URL from route/query details if missing:
            if (!isset($link['url'])) {
                $routeParams = $link['routeParams'] ?? [];

                $link['url'] = $serverUrlHelper(
                    $urlHelper($link['route'], $routeParams)
                );
                if (isset($link['queryString'])) {
                    $link['url'] .= $link['queryString'];
                }
            }

            // Apply prefix if found
            if (isset($link['prefix'])) {
                $link['url'] = $link['prefix'] . $link['url'];
            }
            // Use URL as description if missing:
            if (!isset($link['desc'])) {
                $link['desc'] = $link['url'];
            }

            return $link;
        };

        return $this->deduplicateLinks(array_map($formatLink, $urls));
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
