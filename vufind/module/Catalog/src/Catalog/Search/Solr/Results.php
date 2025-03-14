<?php

/**
 * Solr aspect of the Search Multi-class (Results)
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2011, 2022.
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
 * @package  Search_Solr
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace Catalog\Search\Solr;

use VuFind\Search\Solr\AbstractErrorListener as ErrorListener;
use VuFindSearch\Command\SearchCommand;
use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\Query\QueryGroup;

use function count;

/**
 * Solr Search Parameters
 *
 * @category VuFind
 * @package  Search_Solr
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Results extends \VuFind\Search\Solr\Results implements \Laminas\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Support method for performAndProcessSearch -- perform a search based on the
     * parameters passed to the object.
     *
     * @return void
     */
    protected function performSearch()
    {
        $query  = $this->getParams()->getQuery();
        $limit  = $this->getParams()->getLimit();
        $offset = $this->getStartRecord() - 1;
        $params = $this->getParams()->getBackendParameters();
        $searchService = $this->getSearchService();
        $cursorMark = $this->getCursorMark();
        if (null !== $cursorMark) {
            $params->set('cursorMark', '' === $cursorMark ? '*' : $cursorMark);
            // Override any default timeAllowed since it cannot be used with
            // cursorMark
            $params->set('timeAllowed', -1);
        }

        try {
            $this->logError('=== In MSUL perofrmSearch ===');
            $command = new SearchCommand(
                $this->backendId,
                $query,
                $offset,
                $limit,
                $params
            );

            $collection = $searchService->invoke($command)->getResult();
        } catch (\VuFindSearch\Backend\Exception\BackendException $e) {
            $this->logError('=== In MSUL BackendException === ');
            // If the query caused a parser error, see if we can clean it up:
            if (
                $newQuery = $this->fixBadQuery($query)
            ) {
                // We need to get a fresh set of $params, since the previous one was
                // manipulated by the previous search() call.
                $this->logError('=== In MSUL Retrying query... ===');
                $params = $this->getParams()->getBackendParameters();
                $command = new SearchCommand(
                    $this->backendId,
                    $newQuery,
                    $offset,
                    $limit,
                    $params
                );
                $collection = $searchService->invoke($command)->getResult();
            } else {
                throw $e;
            }
        }

        $this->extraSearchBackendDetails = $command->getExtraRequestDetails();

        $this->responseFacets = $collection->getFacets();
        $this->filteredFacetCounts = $collection->getFilteredFacetCounts();
        $this->responseQueryFacets = $collection->getQueryFacets();
        $this->responsePivotFacets = $collection->getPivotFacets();
        $this->resultTotal = $collection->getTotal();
        $this->maxScore = $collection->getMaxScore();

        // Process spelling suggestions
        $spellcheck = $collection->getSpellcheck();
        $this->spellingQuery = $spellcheck->getQuery();
        $this->suggestions = $this->getSpellingProcessor()
            ->getSuggestions($spellcheck, $this->getParams()->getQuery());

        // Update current cursorMark
        if (null !== $cursorMark) {
            $this->setCursorMark($collection->getCursorMark());
        }

        // Construct record drivers for all the items in the response:
        $this->results = $collection->getRecords();

        // Store any errors:
        $this->errors = $collection->getErrors();
    }
}
