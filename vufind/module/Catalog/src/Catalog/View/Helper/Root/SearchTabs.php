<?php

/**
 * TODO -- REMOVE AFTER PR 4678 IS RELEASED
 * "Search tabs" view helper
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2015-2016.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Catalog\View\Helper\Root;

/**
 * "Search tabs" view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class SearchTabs extends \VuFind\View\Helper\Root\SearchTabs implements \Laminas\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Determine information about search tabs
     *
     * @param string $activeSearchClass The search class ID of the active search
     * @param string $query             The current search query
     * @param string $handler           The current search handler
     * @param string $type              The current search type (basic/advanced)
     * @param array  $hiddenFilters     The current hidden filters
     *
     * @return array
     */
    public function getTabConfig(
        $activeSearchClass,
        $query,
        $handler,
        $type = 'basic',
        $hiddenFilters = []
    ) {
        $retVal = ['tabs' => []];
        $allFilters = $this->helper->getTabFilterConfig();
        $allPermissions = $this->helper->getTabPermissionConfig();
        $allSettings = $this->helper->getSettings();
        $retVal['showCounts'] = $allSettings['show_result_counts'] ?? false;
        foreach ($this->helper->getTabConfig() as $key => $label) {
            $permissionName = null;
            if (isset($allPermissions[$key])) {
                $permissionName = $allPermissions[$key];
            }
            $class = $this->helper->extractClassName($key);
            $filters = isset($allFilters[$key]) ? (array)$allFilters[$key] : [];
            $selected = $class == $activeSearchClass && $this->helper->filtersMatch($class, $hiddenFilters, $filters);
            // MSUL -- PR CHANGE
            try {
                if ($type == 'basic') {
                    if (!isset($activeOptions)) {
                        $activeOptions
                            = $this->results->get($activeSearchClass)->getOptions();
                    }
                    $url = $this->remapBasicSearch(
                        $activeOptions,
                        $class,
                        $query,
                        $handler,
                        $filters,
                    );
                } elseif ($type == 'advanced') {
                    $url = $this->getAdvancedTabUrl(
                        $class,
                        $filters,
                    );
                } else {
                    $url = $this->getHomeTabUrl(
                        $class,
                        $filters,
                    );
                }
            } catch (\Exception $e) {
                // Log the error and just don't add tabs that we couldn't get the data for
                $baseMsg = "Could not add tab for {$key}.";
                $shortDetails = $e->getMessage();
                $fullDetails = (string)$e;
                $this->logError([
                    1 => "$baseMsg $shortDetails",
                    2 => "$baseMsg $shortDetails",
                    3 => "$baseMsg $shortDetails",
                    4 => "$baseMsg $fullDetails",
                    5 => "$baseMsg $fullDetails",
                ], prependClass: false);
                continue;
            }
            $tab = [
                'id' => $key,
                'class' => $class,
                'label' => $label,
                'permission' => $permissionName,
                'selected' => $selected,
                'url' => $url,
            ];
            $retVal['tabs'][] = $tab;
            if ($selected) {
                $retVal['selected'] = $tab;
            }
        }

        return $retVal;
    }
}
