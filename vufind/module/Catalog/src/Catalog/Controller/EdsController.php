<?php

/**
 * Eds Controller
 *
 * PHP version 8
 *
 * @category VuFind
 * @package  Controller
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */

namespace Catalog\Controller;

/**
 * Helper class for the GetThis Loader containing
 * The action for when the button is clicked
 *
 * @category VuFind
 * @package  Controller
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class EdsController extends \VuFind\Controller\EdsController
{
    /**
     * Process the facets to be used as limits on the Advanced Search screen.
     *
     * @param array  $facetList    The advanced facet values
     * @param object $searchObject Saved search object (false if none)
     *
     * @return array               Sorted facets, with selected values flagged.
     */
    protected function processAdvancedFacets($facetList, $searchObject = false)
    {
        // Process the facets, assuming they came back
        foreach ($facetList as $facet => $list) {
            if (isset($list['LimiterValues'])) {
                foreach ($list['LimiterValues'] as $key => $value) {
                    // Build the filter string for the URL:
                    $fullFilter = $facet . ':' . $value['Value'];

                    // If we haven't already found a selected facet and the current
                    // facet has been applied to the search, we should store it as
                    // the selected facet for the current control.
                    if ($searchObject) {
                        $limitFilt = 'LIMIT|' . $fullFilter;
                        if ($searchObject->getParams()->hasFilter($limitFilt)) {
                            $facetList[$facet]['LimiterValues'][$key]['selected']
                                = true;
                            // Remove the filter from the search object -- we don't
                            // want it to show up in the "applied filters" sidebar
                            // since it will already be accounted for by being
                            // selected in the filter select list!
                            $searchObject->getParams()->removeFilter($limitFilt);
                        }
                    } else {
                        if ('y' == $facetList[$facet]['DefaultOn']) {
                            $facetList[$facet]['selected'] = true;
                        }
                    }
                }
            }
        }
        foreach ($facetList as &$facet) {
            usort($facet['LimiterValues'], function ($a, $b) {
                return strtolower($a['Value']) <=> strtolower($b['Value']);
            });
        }
        return $facetList;
    }
}
