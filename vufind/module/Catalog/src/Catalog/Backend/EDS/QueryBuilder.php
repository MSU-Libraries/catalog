<?php

namespace Catalog\Backend\EDS;
use VuFindSearch\ParamBag;
use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\Query\Query;
use VuFindSearch\Query\QueryGroup;


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
        // MSUL Override to to exclude specific items already indexed in Solr
        $expression = $expression . ' NOT (LN cat09242a OR LO system.nl-s8364774 OR LN cat09276a)';
        return json_encode(
            ['term' => $expression, 'field' => $fieldCode, 'bool' => $operator]
        );
    }
}
