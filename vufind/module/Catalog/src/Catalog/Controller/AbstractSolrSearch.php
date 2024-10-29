<?php

/**
 * TODO
 *  THIS IS AN EXACT COPY OF THE CORE-VUFIND FILE FOR EXTENSION PURPOSES
 *  COULD BE REMOVED WHEN PR ARE ACCEPTED (PC-895 + PC-698)
 * AbstractSearch with Solr-specific features added.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace Catalog\Controller;

use VuFind\Controller\Feature\RecordVersionsSearchTrait;

use function in_array;

/**
 * AbstractSearch with Solr-specific features added.
 *
 * @category VuFind
 * @package  Controller
 * @author   Robby ROUDON <roudonro@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class AbstractSolrSearch extends \Catalog\Controller\AbstractSearch
{
    /**
     * Process the facets to be used as limits on the Advanced Search screen.
     *
     * @param array  $facetList                     The advanced facet values
     * @param object $searchObject                  Saved search object
     * (false if none)
     * @param array  $hierarchicalFacets            Hierarchical facet list (if any)
     * @param array  $hierarchicalFacetsSortOptions Hierarchical facet sort options
     * (if any)
     *
     * @return array Sorted facets, with selected values flagged.
     */
    protected function processAdvancedFacets(
        $facetList,
        $searchObject = false,
        $hierarchicalFacets = [],
        $hierarchicalFacetsSortOptions = []
    ) {
        $facetHelper = null;
        $options = null;
        foreach ($facetList as $facet => &$list) {
            // Hierarchical facets: format display texts and sort facets
            // to a flat array according to the hierarchy
            if (in_array($facet, $hierarchicalFacets)) {
                // Process the facets
                if (!$facetHelper) {
                    $facetHelper = $this->serviceLocator
                        ->get(\VuFind\Search\Solr\HierarchicalFacetHelper::class);
                    $options = $this->getOptionsForClass();
                }

                $tmpList = $list['list'];

                // MSU START
                $sort = $hierarchicalFacetsSortOptions[$facet]
                    ?? $hierarchicalFacetsSortOptions['*'] ?? 'top';

                $facetHelper->sortFacetList($tmpList, $sort);
                $tmpList = $facetHelper->buildFacetArray(
                    $facet,
                    $tmpList
                );
                // MSU End
                $list['list'] = $facetHelper->flattenFacetHierarchy($tmpList);
            }

            foreach ($list['list'] as $key => $value) {
                // Build the filter string for the URL:
                $fullFilter = ($value['operator'] == 'OR' ? '~' : '')
                    . $facet . ':"' . $value['value'] . '"';

                // If we haven't already found a selected facet and the current
                // facet has been applied to the search, we should store it as
                // the selected facet for the current control.
                if (
                    $searchObject
                    && $searchObject->getParams()->hasFilter($fullFilter)
                ) {
                    $list['list'][$key]['selected'] = true;
                    // Remove the filter from the search object -- we don't want
                    // it to show up in the "applied filters" sidebar since it
                    // will already be accounted for by being selected in the
                    // filter select list!
                    $searchObject->getParams()->removeFilter($fullFilter);
                }
            }
        }
        return $facetList;
    }
}
