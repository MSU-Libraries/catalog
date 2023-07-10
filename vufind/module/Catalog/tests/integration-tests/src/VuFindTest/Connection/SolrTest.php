<?php

/**
 * Solr Connection Test Class
 *
 * PHP version 7
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
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace CatalogTest\Integration\Connection;

use VuFindSearch\ParamBag;

/**
 * Solr Connection Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SolrTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\LiveDetectionTrait;
    use \VuFindTest\Feature\LiveSolrTrait;

    /**
     * Check AlphaBrowse "see also" functionality.
     *
     * @return void
     */
    public function testAlphaBrowseSeeAlso()
    {
        $solr = $this->getBackend();
        $extras = new ParamBag(['extras' => 'id']);
        $result = $solr->alphabeticBrowse('author', 'Dublin Society', 0, 1, $extras);
        $item = $result['Browse']['items'][0];
        $this->assertEquals($item['count'], count($item['extras']['id']));
        $this->assertTrue(empty($item['useInstead']));
        $this->assertTrue(in_array(['folio.in00003688132'], $item['extras']['id']));
        $this->assertTrue(in_array('Royal Dublin Society', $item['seeAlso']));
        $this->assertEquals('Dublin Society', $item['heading']);
    }

    /**
     * Check AlphaBrowse "use instead" functionality.
     *
     * @return void
     */
    public function testAlphaBrowseUseInstead()
    {
        $solr = $this->getBackend();
        $extras = new ParamBag(['extras' => 'id']);
        $result = $solr
            ->alphabeticBrowse('author', 'Dublin Society, Royal', 0, 1, $extras);
        $item = $result['Browse']['items'][0];
        $this->assertEquals(0, $item['count']);
        $this->assertEquals($item['count'], count($item['extras']['id']));
        $this->assertEquals('Dublin Society, Royal', $item['heading']);
        $this->assertTrue(empty($item['seeAlso']));
        $this->assertTrue(in_array('Royal Dublin Society', $item['useInstead']));
    }
}
