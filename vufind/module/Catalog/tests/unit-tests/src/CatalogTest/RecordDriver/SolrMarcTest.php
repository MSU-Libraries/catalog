<?php

/**
 * SolrMarc Record Driver Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018.
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
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace CatalogTest\RecordDriver;

use Catalog\RecordDriver\SolrMarc;
use Laminas\Config\Config;

use function strlen;

/**
 * SolrMarc Record Driver Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SolrMarcTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;
    use \VuFindTest\Feature\ReflectionTrait;

    /**
     * Get a record driver with fake data.
     *
     * @param string $marcFixture Name of the fixture file containing a MARC record.
     * @param string $solrFixture Name of the fixture file containing a Solr record.
     * @param Config $mainConfig  Main configuration (optional).
     *
     * @return SolrDefault
     */
    protected function getDriver(
        $marcFixture = 'marctraits.xml',
        $solrFixture = 'testbug2.json',
        Config $mainConfig = null
    ) {
        $fixture = $this->getJsonFixture('misc/' . $solrFixture);
        $record = new SolrMarc($mainConfig);
        $marc = [];
        if (!empty($marcFixture)) {
            $marc = ['fullrecord' => $this->getFixture('marc/' . $marcFixture, 'Catalog')];
        }
        $record->setRawData($marc + $fixture['response']['docs'][0]);
        return $record;
    }

    /**
     * Test that summary notes are retrieved from Solr
     *
     * @return void
     */
    public function testGetSummaryNotes()
    {
        $this->assertEquals(['Summary. Expanded.'], $this->getDriver()->getSummaryNotes());
    }

    /**
     * Test that subject headings are retrieved in the correct order from the marc record.
     *
     * @return void
     */
    public function testGetAllSubjectHeadings()
    {
        $this->assertCount(3, $this->getDriver('linkedauthors.xml')->getAllSubjectHeadings()[0]);
        $this->assertEquals('1814-1841', $this->getDriver('linkedauthors.xml')->getAllSubjectHeadings()[0][1]);
    }

    /**
     * Test that no failure happens when passing an invalid marc field to getMarcFieldLinked
     *
     * @return void
     */
    public function testGetMarcFieldLinkedNotFound()
    {
        // Verifying the count since the values non-printable characters
        $this->assertEquals([], $this->getDriver('linkedauthors.xml')->getMarcFieldLinked('90', ['a']));
    }

    /**
     * Test that the correct data is returned from the marc field to getMarcFieldLinked
     *
     * @return void
     */
    public function testGetMarcFieldLinked()
    {
        // Verifying the count since the values non-printable characters
        $this->assertCount(2, $this->getDriver('linkedauthors.xml')->getMarcFieldLinked('700', ['a']));
        $this->assertEquals(47, strlen($this->getDriver('linkedauthors.xml')->getMarcFieldLinked('700', ['a'])[0]));
    }

    /**
     * Test that the linked 880 fields are retrieved from the marc record.
     *
     * @return void
     */
    public function testGetPrimaryAuthorsLinks()
    {
        // Verifying the count since the values non-printable characters
        $this->assertCount(1, $this->getDriver('linkedauthors2.xml')->getPrimaryAuthorsLinks());
        $this->assertEquals(30, strlen($this->getDriver('linkedauthors2.xml')->getPrimaryAuthorsLinks()[0]));
    }

    /**
     * Test that the linked 880 fields are retrieved from the marc record.
     *
     * @return void
     */
    public function testGetSecondaryAuthorsLinks()
    {
        // Verifying the count since the values non-printable characters
        $this->assertCount(2, $this->getDriver('linkedauthors.xml')->getSecondaryAuthorsLinks());
        $this->assertEquals(47, strlen($this->getDriver('linkedauthors.xml')->getSecondaryAuthorsLinks()[0]));
    }

    /**
     * Test that the linked 880 fields are retrieved from the marc record.
     *
     * @return void
     */
    public function testGetCorporateAuthorsLinks()
    {
        // Verifying the count since the values non-printable characters
        $this->assertCount(1, $this->getDriver('linkedauthors2.xml')->getCorporateAuthorsLinks());
        $this->assertEquals(94, strlen($this->getDriver('linkedauthors2.xml')->getCorporateAuthorsLinks()[0]));
    }
}
