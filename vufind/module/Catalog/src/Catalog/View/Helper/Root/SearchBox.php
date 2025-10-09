<?php

/**
 * TODO -- REMOVE AFTER PR 4678 IS RELEASED
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

use function count;
use function in_array;

/**
 * Search box view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class SearchBox extends \VuFind\View\Helper\Root\SearchBox implements \Laminas\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Get JSON-encoded configuration for autocomplete query formatting.
     *
     * @param string $activeSearchClass Active search class ID
     *
     * @return string
     */
    public function autocompleteFormattingRulesJson($activeSearchClass): string
    {
        if ($this->combinedHandlersActive()) {
            $rules = [];
            $settings = $this->getCombinedHandlerConfig($activeSearchClass);
            foreach ($settings['target'] ?? [] as $i => $target) {
                if (($settings['type'][$i] ?? null) === 'VuFind') {
                    // MSUL -- PR CHANGE
                    try {
                        $options = $this->optionsManager->get($target);
                        $handlerRules = $options->getAutocompleteFormattingRules() ?? [];
                        foreach ($handlerRules as $key => $val) {
                            $rules["VuFind:$target|$key"] = $val;
                        }
                    } catch (\Exception $e) {
                        // Log a warning and ignore when we can't add the autocomplete rules for
                        // any of the handlers
                        $baseMsg = "Could not determine autocomplete formatting rules for {$target}.";
                        $shortDetails = $e->getMessage();
                        $fullDetails = (string)$e;
                        $this->logWarning([
                            1 => "$baseMsg $shortDetails",
                            2 => "$baseMsg $shortDetails",
                            3 => "$baseMsg $shortDetails",
                            4 => "$baseMsg $fullDetails",
                            5 => "$baseMsg $fullDetails",
                        ], prependClass: false);
                    }
                }
            }
        } else {
            $options = $this->optionsManager->get($activeSearchClass);
            $rules = $options->getAutocompleteFormattingRules();
        }
        return json_encode($rules);
    }

    /**
     * Support method for getHandlers() -- load combined settings.
     *
     * @param string $activeSearchClass Active search class ID
     * @param string $activeHandler     Active search handler
     *
     * @return array
     */
    protected function getCombinedHandlers($activeSearchClass, $activeHandler)
    {
        // Build settings:
        $handlers = [];
        $backupSelectedIndex = false;
        $addedBrowseHandlers = false;
        $settings = $this->getCombinedHandlerConfig($activeSearchClass);
        $typeCount = count($settings['type']);
        for ($i = 0; $i < $typeCount; $i++) {
            $type = $settings['type'][$i];
            $target = $settings['target'][$i];
            $label = $settings['label'][$i];

            if ($type == 'VuFind') {
                $j = 0;
                // MSUL -- PR CHANGE
                try {
                    $options = $this->optionsManager->get($target);
                    $basic = $options->getBasicHandlers();
                } catch (\Exception $e) {
                    // If we can't get the options or basic handlers for the search
                    // target, then log it and don't add it to the search box
                    $baseMsg = "Missing required data for {$target}. Could not add to search box.";
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
                if (empty($basic)) {
                    $basic = ['' => ''];
                }
                foreach ($basic as $searchVal => $searchDesc) {
                    $j++;
                    $selected = $target == $activeSearchClass
                        && $activeHandler == $searchVal;
                    if (
                        !$selected
                        && $backupSelectedIndex === false
                        && $target == $activeSearchClass
                    ) {
                        $backupSelectedIndex = count($handlers);
                    }
                    // Depending on whether or not the current section has a label,
                    // we'll either want to override the first label and indent
                    // subsequent ones, or else use all default labels without
                    // any indentation.
                    if (empty($label)) {
                        $finalLabel = $searchDesc;
                        $indent = false;
                    } else {
                        $finalLabel = $j == 1 ? $label : $searchDesc;
                        $indent = $j == 1 ? false : true;
                    }
                    $handlers[] = [
                        'value' => $type . ':' . $target . '|' . $searchVal,
                        'label' => $finalLabel,
                        'indent' => $indent,
                        'selected' => $selected,
                        'group' => $settings['group'][$i],
                    ];
                }

                // Should we add alphabrowse links?
                if ($target === 'Solr' && $this->alphaBrowseOptionsEnabled()) {
                    $addedBrowseHandlers = true;
                    $handlers = array_merge(
                        $handlers,
                        // Only indent alphabrowse handlers if label is non-empty:
                        $this->getAlphaBrowseHandlers($activeHandler, !empty($label))
                    );
                }
            } elseif ($type == 'External') {
                $handlers[] = [
                    'value' => $type . ':' . $target, 'label' => $label,
                    'indent' => false, 'selected' => false,
                    'group' => $settings['group'][$i],
                ];
            }
        }

        // If we didn't add alphabrowse links above as part of the Solr section
        // but we are configured to include them, we should add them now:
        if (!$addedBrowseHandlers && $this->alphaBrowseOptionsEnabled()) {
            $handlers = array_merge(
                $handlers,
                $this->getAlphaBrowseHandlers($activeHandler, false)
            );
        }

        // If we didn't find an exact match for a selected index, use a fuzzy
        // match (do the check here since it could be an AlphaBrowse index too):
        $selectedFound = in_array(true, array_column($handlers, 'selected'), true);
        if (!$selectedFound && $backupSelectedIndex !== false) {
            $handlers[$backupSelectedIndex]['selected'] = true;
        }
        return $handlers;
    }
}
