<?php

/**
 * Overrides for the Articles & More search tab searching
 * the EDS API
 *
 * PHP version 7
 *
 * @category VuFind
 * @package  Backend_EDS
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/vufind/ Main page
 */

namespace Catalog\Backend\EDS;

use VuFindSearch\Query\Query;

/**
 * Class that represents queries to the EDS API
 *
 * @category VuFind
 * @package  Backend_EDS
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/vufind/ Main page
 */
class QueryBuilder extends \VuFindSearch\Backend\EDS\QueryBuilder
{
    /**
     * Convert a single Query object to an eds api query array
     *
     * @param Query  $query    Query to convert
     * @param string $operator Operator to apply
     *
     * @return array
     */
    protected function queryToEdsQuery(Query $query, $operator = 'AND')
    {
        $expression = $query->getString();
        $fieldCode = ($query->getHandler() == 'AllFields')
            ? '' : $query->getHandler();  //fieldcode
        // Special case: default search
        if (empty($fieldCode) && empty($expression)) {
            $expression = $this->defaultQuery;
        }
        // MSUL Override to exclude specific items already indexed in Solr
        $expression = $expression . ' NOT (LN cat09242a OR LO system.nl-s8364774 OR LN cat09276a)';
        return json_encode(
            ['term' => $expression, 'field' => $fieldCode, 'bool' => $operator]
        );
    }
}
