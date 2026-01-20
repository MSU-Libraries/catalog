<?php

/**
 * Extends the Solr QueryBuilder to handle queries with exact parts (PC-1383).
 *
 * PHP version 8
 *
 * @category VuFind
 * @package  Search
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace Catalog\Backend\Solr;

use VuFindSearch\ParamBag;
use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\Query\Query;
use VuFindSearch\Query\QueryGroup;

/**
 * SOLR QueryBuilder.
 *
 * @category VuFind
 * @package  Search
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class QueryBuilder extends \VuFindSearch\Backend\Solr\QueryBuilder
{
    /**
     * Return SOLR search parameters based on a user query and params.
     *
     * @param AbstractQuery $query  User query
     * @param ?ParamBag     $params Search backend parameters
     *
     * @return ParamBag
     */
    public function build(AbstractQuery $query, ?ParamBag $params = null)
    {
        $query = $this->possiblyConvertMixedExactQueryIntoAdvanced($query);
        return parent::build($query, $params);
    }

    /**
     * Converts a simple query (Query) into an advanced one (QueryGroup) if part of it should be an exact query.
     * This only supports a single exact query (surrounded with quotes) combined with a non-exact query.
     * Logical operators can be used, but not parentheses or field names.
     * The original query is returned for any non-supported case.
     *
     * @param AbstractQuery $query User query
     *
     * @return AbstractQuery
     */
    protected function possiblyConvertMixedExactQueryIntoAdvanced(AbstractQuery $query): AbstractQuery
    {
        if (!($query instanceof Query)) {
            return $query;
        }
        $handler = $query->getHandler();
        if ($handler && !isset($this->exactSpecs[strtolower($handler)])) {
            return $query;
        }
        $queryString = trim($query->getString());
        if (!preg_match('/^([^":()+]*)"([^"]+)"([^":()]*)$/u', $queryString, $parts)) {
            return $query;
        }
        $groupOperator = 'AND';
        $negateQuotedPart = false;
        $before = trim($parts[1]);
        if (preg_match('/^(.+\s+)?(NOT|-)$/u', $before, $notParts)) {
            $before = trim($notParts[1]);
            $negateQuotedPart = true;
        }
        if (preg_match('/^(.*)\s+(AND|OR)$/u', $before, $beforeParts)) {
            $before = $beforeParts[1];
            $groupOperator = $beforeParts[2];
        }
        $quoted = '"' . $parts[2] . '"';
        $after = trim($parts[3]);
        if (preg_match('/^(AND|OR)\s*(.*)$/u', $after, $afterParts)) {
            $groupOperator = $afterParts[1];
            $after = $afterParts[2];
        }
        if (($before == '' && $after == '') || ($before != '' && $after != '')) {
            return $query;
        }
        $subQueries = [];
        if ($before != '') {
            $subQueries[] = new Query($before, $handler);
        }
        if ($negateQuotedPart) {
            $subQueries[] = new QueryGroup('NOT', [ new Query($quoted, $handler) ]);
        } else {
            $subQueries[] = new Query($quoted, $handler);
        }
        if ($after != '') {
            $subQueries[] = new Query($after, $handler);
        }
        return new QueryGroup($groupOperator, $subQueries);
    }
}
