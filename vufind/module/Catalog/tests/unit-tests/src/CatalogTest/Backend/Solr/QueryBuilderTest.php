<?php

/**
 * Unit tests for SOLR query builder
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
 * @link     https://vufind.org
 */

namespace VuFindTest\Backend\Solr;

use Catalog\Backend\Solr\QueryBuilder;
use VuFindSearch\Query\Query;

/**
 * Unit tests for SOLR query builder
 *
 * @category VuFind
 * @package  Search
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class QueryBuilderTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test queries with mixed exact and non-exact parts.
     *
     * @return void
     */
    public function testMixedExactQueryHandler()
    {
        // Check QueryBuilder without ExactSettings
        $qb = new QueryBuilder(
            [
                'TestHandler' => [
                    'DismaxFields' => ['a'],
                    'DismaxHandler' => 'edismax',
                ],
            ]
        );
        $q = new Query('"t1" AND t2', 'TestHandler');
        $response = $qb->build($q);
        $queryString = $response->get('q')[0];
        $this->assertEquals('"t1" AND t2', $queryString);

        // Expected inputs and outputs with ExactSettings:
        $tests = [
            ['"t1"', '"t1"'], // simple exact queries are not affected
            ['("t1" OR t2) AND t3', '("t1" OR t2) AND t3'], // queries with parenthesis are not supported
            ['"t1" AND title:t2', '"t1" AND title:t2'], // queries with field are not supported
            ['"t1" AND "t2"', '"t1" AND "t2"'], // queries with multiple exact parts are not supported
            ['t1 AND "t2" AND t3', 't1 AND "t2" AND t3'], // queries with an exact part in the middle are not supported
            ['"t1" t2', '((_query_:"{!edismax qf=\"b\" mm=\\\'0%\\\'}\"t1\"") AND ' .
                '(_query_:"{!edismax qf=\"a\" mm=\\\'0%\\\'}t2"))'],
            ['"t1" AND t2', '((_query_:"{!edismax qf=\"b\" mm=\\\'0%\\\'}\"t1\"") AND ' .
                '(_query_:"{!edismax qf=\"a\" mm=\\\'0%\\\'}t2"))'],
            ['"t1" OR t2', '((_query_:"{!edismax qf=\"b\" mm=\\\'0%\\\'}\"t1\"") OR ' .
                '(_query_:"{!edismax qf=\"a\" mm=\\\'0%\\\'}t2"))'],
            ['t1 AND "t2"', '((_query_:"{!edismax qf=\"a\" mm=\\\'0%\\\'}t1") AND ' .
                '(_query_:"{!edismax qf=\"b\" mm=\\\'0%\\\'}\"t2\""))'],
            ['NOT "t1" AND t2', '((*:* NOT ((_query_:"{!edismax qf=\"b\" mm=\\\'0%\\\'}\"t1\""))) AND ' .
                '(_query_:"{!edismax qf=\"a\" mm=\\\'0%\\\'}t2"))'],
            ['t1 AND NOT "t2"', '((_query_:"{!edismax qf=\"a\" mm=\\\'0%\\\'}t1") AND ' .
                '(*:* NOT ((_query_:"{!edismax qf=\"b\" mm=\\\'0%\\\'}\"t2\""))))'],
            ['-"t1" t2', '((*:* NOT ((_query_:"{!edismax qf=\"b\" mm=\\\'0%\\\'}\"t1\""))) AND ' .
                '(_query_:"{!edismax qf=\"a\" mm=\\\'0%\\\'}t2"))'],
            ['"t1" AND t2 AND t3', '((_query_:"{!edismax qf=\"b\" mm=\\\'0%\\\'}\"t1\"") AND ' .
                '(_query_:"{!edismax qf=\"a\" mm=\\\'0%\\\'}t2 AND t3"))'], // would be different with dismax
        ];

        $qb = new QueryBuilder(
            [
                'TestHandler' => [
                    'DismaxFields' => ['a'],
                    'DismaxHandler' => 'edismax',
                    'ExactSettings' => [
                        'DismaxFields' => ['b'],
                        'DismaxHandler' => 'edismax',
                    ],
                ],
            ]
        );

        foreach ($tests as $test) {
            [$input, $output] = $test;
            $q = new Query($input, 'TestHandler');
            $response = $qb->build($q);
            $queryString = $response->get('q')[0];
            $this->assertEquals($output, $queryString);
        }
    }

}
