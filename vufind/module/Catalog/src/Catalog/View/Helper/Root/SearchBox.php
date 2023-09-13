<?php

/**
 * Search box view helper
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
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Catalog\View\Helper\Root;

/**
 * Search box view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class SearchBox extends \VuFind\View\Helper\Root\SearchBox
{
    /**
     * Get number of active filters.
     * 
     * Modified to return 0 if the only filter is "Michigan State University" or
     * "Also search within the full text of the articles" (for EDS)
     *
     * @param array $checkboxFilters Checkbox filters
     * @param array $filterList      Other filters
     *
     * @return int
     */
    public function getFilterCount($checkboxFilters, $filterList)
    {
        $totalCount = parent::getFilterCount($checkboxFilters, $filterList);
        if ($totalCount == 1) {
            if (count($filterList) == 1) {
                if (array_key_first($filterList) == 'Institution') {
                    $filter = $filterList['Institution'];
                    if (count($filter) == 1 && $filter[0]['displayText'] == 'Michigan State University') {
                        return 0;
                    }
                }
            } else {
                foreach ($checkboxFilters as $filter) {
                    if ($filter['selected'] && $filter['desc'] == 'eds_expander_fulltext') {
                        return 0;
                    }
                }
            }
        }
        return $totalCount;
    }
}
