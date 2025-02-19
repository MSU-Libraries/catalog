<?php

/**
 * Solr Autocomplete Module PubCat customization
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
 * @package  Autocomplete
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:autosuggesters Wiki
 */

namespace Catalog\Autocomplete;

use function array_slice;
use function is_object;

/**
 * Solr Autocomplete Module
 *
 * This class provides suggestions by using the local Solr index.
 *
 * @category VuFind
 * @package  Autocomplete
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:autosuggesters Wiki
 */
class Solr extends \VuFind\Autocomplete\Solr
{
    /**
     * Process the user query to make it suitable for a Solr query.
     *
     * @param string $query   Incoming user query
     * @param array  $options Array of extra parameters
     *
     * @return string        Processed query
     */
    protected function mungeQuery(string $query, array $options = []): string
    {
        // Modify the query so it uses a wildcard at the end if the last character is alphanumeric:
        if (preg_match('/[\p{L}\p{N}_]$/u', $query)) {
            // When the query ends with a word with a hyphen or date range,
            // replace it with a space before adding a wildcard
            // (Solr does not tokenize a word with a hyphen if it ends with a wildcard)
            $query = preg_replace('/([\p{L}\p{N}_])-([\p{L}\p{N}_]+)$/u', '$1 $2', $query);
            $query .= '*';
        }
        return $query;
    }

    /**
     * This method returns an array of strings matching the user's query for
     * display in the autocomplete box.
     *
     * @param string $query The user query
     *
     * @return array        The suggestions for the provided query
     */
    public function getSuggestions($query)
    {
        $results = null;
        if (!is_object($this->searchObject)) {
            throw new \Exception('Please set configuration first.');
        }

        try {
            $this->searchObject->getParams()->setBasicSearch(
                $this->mungeQuery($query),
                $this->handler
            );
            $this->searchObject->getParams()->setSort($this->sortField);
            foreach ($this->filters as $current) {
                $this->searchObject->getParams()->addFilter($current);
            }

            // Perform the search:
            $searchResults = $this->searchObject->getResults();

            // Build the recommendation list
            $results = $this->getSuggestionsFromSearch2($searchResults, $query);
        } catch (\Exception $e) {
            // Ignore errors -- just return empty results if we must.
        }
        return isset($results) ? array_slice(array_unique($results), 0, 10) : [];
    }

    /**
     * Try to turn an array of record drivers into an array of suggestions.
     * Return exact matches first.
     *
     * @param array  $searchResults An array of record drivers
     * @param string $query         User search query
     *
     * @return array
     */
    protected function getSuggestionsFromSearch2($searchResults, $query)
    {
        $exactMatches = [];
        $otherMatches = [];
        foreach ($searchResults as $object) {
            $current = $object->getRawData();
            foreach ($this->displayField as $field) {
                if (isset($current[$field])) {
                    $exactMatch = $this->pickBestMatch(
                        $current[$field],
                        $query,
                        true
                    );
                    if ($exactMatch) {
                        $exactMatches[] = $exactMatch;
                        break;
                    }
                    $otherMatch = $this->pickBestMatch(
                        $current[$field],
                        $query,
                        false
                    );
                    if ($otherMatch) {
                        $otherMatches[] = $otherMatch;
                        break;
                    }
                }
            }
        }
        return array_merge($exactMatches, $otherMatches);
    }
}
