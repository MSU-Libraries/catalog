<?php

/**
 * TODO - REMOVE WHEN PR 4678 IS RELEASED
 * Combined Search Controller
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2011.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.    See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace Catalog\Controller;

use VuFind\Search\SearchRunner;

use function count;
use function in_array;
use function intval;
use function is_array;

/**
 * Redirects the user to the appropriate default VuFind action.
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class CombinedController extends \VuFind\Controller\CombinedController implements \Laminas\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Results action
     *
     * @return mixed
     */
    public function resultsAction()
    {
        // Set up current request context:
        $request = $this->getRequest()->getQuery()->toArray()
            + $this->getRequest()->getPost()->toArray();
        $results = $this->getService(SearchRunner::class)->run(
            $request,
            'Combined',
            $this->getSearchSetupCallback()
        );

        // Remember the current URL, then disable memory so multi-search results
        // don't overwrite it:
        $this->rememberSearch($results);
        $this->getSearchMemory()->disable();

        // Gather combined results:
        $combinedResults = [];
        $optionsManager = $this->getService(\VuFind\Search\Options\PluginManager::class);
        $combinedOptions = $optionsManager->get('combined');
        // Save the initial type value, since it may get manipulated below:
        $initialType = $this->params()->fromQuery('type');
        foreach ($combinedOptions->getTabConfig() as $current => $settings) {
            [$searchClassId] = explode(':', $current);
            // MSUL PR CHANGE
            try {
                $currentOptions = $optionsManager->get($searchClassId);
                $this->adjustQueryForSettings(
                    $settings,
                    $currentOptions->getHandlerForLabel($initialType)
                );
            } catch (\Exception $e) {
                // Prevent errors from any of the combined search results
                // from raising up to the user interface and instead just skip them
                $baseMsg = "Failed get combined options for {$searchClassId}.";
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
            [$controller, $action]
                = explode('-', $currentOptions->getSearchAction());
            $combinedResults[$current] = $settings;

            // Calculate a unique DOM id for this section of the search results;
            // $searchClassId may contain colons, which must be converted.
            $combinedResults[$current]['domId']
                = 'combined_' . str_replace(':', '____', $current);

            $permissionDenied = isset($settings['permission'])
                && !$this->permission()->isAuthorized($settings['permission']);
            $isAjax = $settings['ajax'] ?? false;
            $combinedResults[$current]['view'] = ($permissionDenied || $isAjax)
                ? $this->createViewModel(['results' => $results])
                : $this->forwardTo($controller, $action);

            // Special case: include appropriate "powered by" message:
            if (strtolower($searchClassId) == 'summon') {
                $this->layout()->poweredBy = 'Powered by Summon™ from Serials '
                    . 'Solutions, a division of ProQuest.';
            }
        }

        // Restore the initial type value to the query to prevent weird behavior:
        $this->getRequest()->getQuery()->type = $initialType;

        // Run the search to obtain recommendations:
        $results->performAndProcessSearch();

        $actualMaxColumns = count($combinedResults);
        $config = $this->getService(\VuFind\Config\PluginManager::class)->get('combined')->toArray();
        $columnConfig = intval($config['Layout']['columns'] ?? $actualMaxColumns);
        $columns = min($columnConfig, $actualMaxColumns);
        $placement = $config['Layout']['stack_placement'] ?? 'distributed';
        if (!in_array($placement, ['distributed', 'left', 'right', 'grid'])) {
            $placement = 'distributed';
        }

        // Identify if any modules use include_recommendations_side or
        // include_recommendations_noresults_side.
        $columnSideRecommendations = [];
        $recommendationManager = $this->getService(\VuFind\Recommend\PluginManager::class);
        foreach ($config as $subconfig) {
            foreach (['include_recommendations_side', 'include_recommendations_noresults_side'] as $type) {
                if (is_array($subconfig[$type] ?? false)) {
                    foreach ($subconfig[$type] as $recommendation) {
                        $recommendationModuleName = strtok($recommendation, ':');
                        $recommendationModule = $recommendationManager->get($recommendationModuleName);
                        $columnSideRecommendations[] = str_replace('\\', '_', $recommendationModule::class);
                    }
                }
            }
        }

        // Build view model:
        return $this->createViewModel(
            [
                'columns' => $columns,
                'combinedResults' => $combinedResults,
                'config' => $config,
                'params' => $results->getParams(),
                'placement' => $placement,
                'results' => $results,
                'columnSideRecommendations' => $columnSideRecommendations,
            ]
        );
    }
}
