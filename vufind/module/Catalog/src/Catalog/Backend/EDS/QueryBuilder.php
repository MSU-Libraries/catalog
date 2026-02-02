<?php

/**
 * EDS API Querybuilder
 *
 * PHP version 8
 *
 * Copyright (C) EBSCO Industries 2013
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
 * @package  Backend_EDS
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/vufind/ Main page
 */

namespace Catalog\Backend\EDS;

use VuFindSearch\Query\Query;

/**
 * EDS API Querybuilder
 *
 * @category VuFind
 * @package  Search
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
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
