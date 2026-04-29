<?php

/**
 * Extends the Solr default backend factory to return a custom QueryBuilder (PC-1383).
 *
 * PHP version 8
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
 * @package  Search
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace Catalog\Search\Factory;

use Catalog\Backend\Solr\QueryBuilder;

/**
 * Factory for the default SOLR backend.
 *
 * @category VuFind
 * @package  Search
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class SolrDefaultBackendFactory extends \VuFind\Search\Factory\SolrDefaultBackendFactory
{
    /**
     * Create the query builder.
     *
     * @return QueryBuilder
     */
    protected function createQueryBuilder()
    {
        $specs   = $this->loadSpecs();
        $defaultDismax = $this->getIndexConfig('default_dismax_handler', 'dismax');
        $builder = new QueryBuilder($specs, $defaultDismax);

        // Configure builder:
        $builder->setLuceneHelper($this->createLuceneSyntaxHelper());

        return $builder;
    }
}
