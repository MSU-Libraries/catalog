<?php

/**
 * TODO COULD BE REMOVED WHEN PR ARE ACCEPTED (PC-895 + PC-698)
 * VuFind Search Controller
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
 * @link     https://vufind.org Main Page
 */

namespace Catalog\Controller;

use Catalog\Search\SearchOrigin\SearchOriginFactory;
use Exception;
use Laminas\Http\Response as HttpResponse;
use Laminas\Stdlib\ResponseInterface as Response;
use Laminas\View\Model\ViewModel;

/**
 * VuFind Search Controller
 *
 * @category VuFind
 * @package  Controller
 * @author   Robby ROUDON <roudonro@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class AbstractSearch extends \VuFind\Controller\AbstractSearch
{
    /**
     * Perform a search and send results to a results view
     *
     * @param callable $setupCallback Optional setup callback that overrides the
     * default one
     *
     * @return Response|ViewModel
     */
    protected function getSearchResultsView($setupCallback = null)
    {
        $view = parent::getSearchResultsView($setupCallback);
        $config = $this->getConfig('facets');
        $view->multiFacetsSelection = $config?->get('Advanced_Settings')?->get('multiFacetsSelection') === '1';
        return $view;
    }

    /*
     * Send search results to results view
     *
     * @return Response | ViewModel
     */
    public function resultsAction()
    {
        try {
            $searchOrigin = SearchOriginFactory::createObject($this->params()->fromQuery());
        } catch (Exception) {
            $searchOrigin = null;
        }
        // For the header link
        $this->layout()->setVariable('searchOrigin', $searchOrigin);
        $view = $this->getSearchResultsView();
        // To put in the records URL as we can't extend some classes
        $view->setVariable('searchOrigin', $searchOrigin);
        return $view;
    }

    /**
     * Process the jumpto parameter -- either redirect to a specific record and
     * return view model, or ignore the parameter and return false.
     *
     * @param \VuFind\Search\Base\Results $results Search results object.
     *
     * @return bool|HttpResponse
     */
    protected function processJumpTo($results)
    {
        // Missing/invalid parameter?  Ignore it:
        $jumpto = $this->params()->fromQuery('jumpto');
        if (empty($jumpto) || !is_numeric($jumpto)) {
            return false;
        }

        $recordList = $results->getResults();
        $queryParams = SearchOriginFactory::createObject($this->params()->fromQuery())->getSearchUrlParamsArray() ?? [];
        return isset($recordList[$jumpto - 1])
            ? $this->getRedirectForRecord($recordList[$jumpto - 1], $queryParams) : false;
    }

    /**
     * Process jump to record if there is only one result.
     *
     * @param \VuFind\Search\Base\Results $results Search results object.
     *
     * @return bool|HttpResponse
     */
    protected function processJumpToOnlyResult($results)
    {
        // If "jumpto" is explicitly disabled (set to false, e.g. by combined search),
        // we should NEVER jump to a result regardless of other factors.
        $jumpto = $this->params()->fromQuery('jumpto', true);
        if (
            $jumpto
            && ($this->getConfig()->Record->jump_to_single_search_result ?? false)
            && $results->getResultTotal() == 1
            && $recordList = $results->getResults()
        ) {
            $queryParams = SearchOriginFactory::createObject($this->params()->fromQuery())->getSearchUrlParamsArray()
                ?? [];
            $queryParams['sid'] = $results->getSearchId();
            return $this->getRedirectForRecord(reset($recordList), $queryParams);
        }
        return false;
    }
}
