<?php

/**
 * MSUL customization PC-1187 have it retry the query
 *
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Search_Solr
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace Catalog\Search\Solr;

use VuFind\Search\Solr\ErrorListener;
use VuFindSearch\Command\SearchCommand;

/**
 * Solr Search Parameters
 *
 * @category VuFind
 * @package  Search_Solr
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Results extends \VuFind\Search\Solr\Results implements
    \Psr\Log\LoggerAwareInterface
{
    // MSU customization to add in logging
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
            $command = new SearchCommand(
                $this->backendId,
                $query,
                $offset,
                $limit,
                $params
            );

            $collection = $searchService->invoke($command)->getResult();
        } catch (\VuFindSearch\Backend\Exception\BackendException $e) {
            // MSU customization PC-1187 log a warning for the caught exception
            $this->logWarning('Caught ' . $e::class . ' Message: ' . $e->getMessage());
            $params = $this->getParams()->getBackendParameters();
            // If the query caused a parser error, see if we can clean it up:
            if (
                $e->hasTag(ErrorListener::TAG_PARSER_ERROR)
                && $newQuery = $this->fixBadQuery($query)
            ) {
                // We need to get a fresh set of $params, since the previous one was
                // manipulated by the previous search() call.
                $this->logWarning('Retrying parser error query');
                $command = new SearchCommand(
                    $this->backendId,
                    $newQuery,
                    $offset,
                    $limit,
                    $params
                );
            } else {
                // MSUL customization PC-1187 have it retry the query
                $this->logWarning('Retrying original query in 2 seconds...');
                sleep(2); // Give Solr time to recover
                $command = new SearchCommand(
                    $this->backendId,
                    $query,
                    $offset,
                    $limit,
                    $params
                );
            }
            $collection = $searchService->invoke($command)->getResult();
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
