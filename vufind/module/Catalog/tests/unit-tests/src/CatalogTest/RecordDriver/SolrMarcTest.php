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
        $solrFixture = 'record.json',
        Config $mainConfig = null
    ) {
        $fixture = $this->getJsonFixture('misc/' . $solrFixture, $module = 'Catalog');
        $record = new SolrMarc($mainConfig);
        $marc = [];
        if (!empty($marcFixture)) {
            $marc = ['fullrecord' => $this->getFixture('marc/' . $marcFixture, 'Catalog')];
        }
        $record->setRawData($marc + $fixture['response']['docs'][0]);
        return $record;
    }

    /**
     * Test the getISBNsWithType function
     *
     * @return void
     */
    public function testGetISBNsWithType()
    {
        $this->assertEquals(
            [
                [
                    'isn' => '978-3-16-148410-0',
                    'qual' => 'qual-data',
                    'type' => 'valid',
                ],
                [
                    'isn' => '978-3-16-148410-1',
                    'type' => 'canceled/invalid',
                ],
            ],
            $this->getDriver()->getISBNsWithType()
        );
    }

    /**
     * Test that summary notes are retrieved from Solr
     *
     * @return void
     */
    public function testGetSummaryNotes()
    {
        $this->assertEquals(
            ['Summary. Expanded.',
            'Linked Summary. Expanded.',
            'Linked Summary2. Expanded.'],
            $this->getDriver()->getSummaryNotes()
        );
    }

    /**
     * Test that subject headings are retrieved in the correct order from the marc record.
     *
     * @return void
     */
    public function testGetAllSubjectHeadings()
    {
        $this->assertCount(3, $this->getDriver('linkedauthors.xml')->getAllSubjectHeadings()[0]);
        $this->assertEquals(
            '1814-1841',
            $this->getDriver('linkedauthors.xml')->getAllSubjectHeadings()[0][1]['subject']
        );
    }

    /**
     * Test that subject headings retrieve the linked value if found
     *
     * @return void
     */
    public function testGetAllSubjectHeadingsLinked()
    {
        $subjects = $this->getDriver('linkedsubjects.xml')->getAllSubjectHeadings();
        // Verify total count of subjects
        $this->assertCount(3, $subjects);
        // Verify 1st subject's 2nd part value
        $this->assertEquals(
            '1878-1935.',
            $subjects[0][1]['subject']
        );
        // Verify 1st subject's 1st part has a linked value
        $this->assertEquals(
            54,
            strlen($subjects[0][0]['linked'])
        );
        // Verify the 1st subject's 2nd part does not have a linked value
        $this->assertEquals(
            0,
            strlen($subjects[0][1]['linked'])
        );
    }

    /**
     * Test that series are retrieved with the correct array elements from the marc record.
     *
     * @return void
     */
    public function testSeries()
    {
        $this->assertCount(2, $this->getDriver('series.xml')->getSeries());
        $this->assertEquals(
            '"Da jia xiao shuo" xi lie ;',
            $this->getDriver('series.xml')->getSeries()[0]['name']
        );
        $this->assertEquals(
            '7.',
            $this->getDriver('series.xml')->getSeries()[0]['number']
        );
    }

    /**
     * Test that series with transliterated values in the 880 field are populated.
     *
     * @return void
     */
    public function testLinkedSeries()
    {
        $this->assertCount(1, $this->getDriver('linkedseries.xml')->getSeries());
        $this->assertEquals(
            'Fa zhan he gai ge lan pi shu = Blue book of development and reform',
            $this->getDriver('linkedseries.xml')->getSeries()[0]['name']
        );
        $this->assertEquals(
            62,
            strlen($this->getDriver('linkedseries.xml')->getSeries()[0]['linked'])
        );
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
        $this->assertEquals(108, strlen($this->getDriver('linkedauthors2.xml')->getCorporateAuthorsLinks()[0]));
    }

    /**
     * Test that the linked 880 fields are retrieved from the marc record.
     *
     * @return void
     */
    public function testGetUniformTitleWithLinks()
    {
        // Verifying the count since the values non-printable characters
        $this->assertCount(1, $this->getDriver('linkedtitle.xml')->getUniformTitle());
        $this->assertArrayHasKey('link', $this->getDriver('linkedtitle.xml')->getUniformTitle()[0]);
        $this->assertEquals(36, strlen($this->getDriver('linkedtitle.xml')->getUniformTitle()[0]['link']));
    }

    /**
     * Test getContentsNotes
     *
     * @return void
     */
    public function testgetContentsNotes()
    {
        $this->assertEquals(
            [
                'General Note.',
                'Note. = Linked Contents.',
            ],
            $this->getDriver()->getContentsNotes()
        );
    }

    /**
     * Test getIncompleteContentsNotes
     *
     * @return void
     */
    public function testgetIncompleteContentsNotes()
    {
        $this->assertEquals(
            [
                'Incomplete Note. = Linked Incomplete Contents.',
            ],
            $this->getDriver()->getIncompleteContentsNotes()
        );
    }

    /**
     * Test getPartialContentsNotes
     *
     * @return void
     */
    public function testgetPartialContentsNotes()
    {
        $this->assertEquals(
            [
                'Partial Note. = Linked Partial Contents.',
                'Partial Note 2. = Linked Partial Contents 2.',
            ],
            $this->getDriver()->getPartialContentsNotes()
        );
    }

    /**
     * Test getPartialContentsNotes
     *
     * @return void
     */
    public function testgetPublicationDetails()
    {
        $this->assertEquals(
            [
                // 260
                new \Catalog\RecordDriver\Response\PublicationDetails(
                    '260 Location :',
                    '260 Publisher',
                    '2600',
                    '',
                    '',
                    '',
                ),
                // 264 with 880
                new \Catalog\RecordDriver\Response\PublicationDetails(
                    'Location :',
                    'The Publishers,',
                    '',
                    'Linked Location :',
                    'Linked Publishers,',
                    'Linked 2020',
                ),
                // 264 with no 880
                new \Catalog\RecordDriver\Response\PublicationDetails(
                    'Location 2:',
                    '',
                    '2020',
                    '',
                    '',
                    '',
                ),
                // 880 with no corresponding 264
                new \Catalog\RecordDriver\Response\PublicationDetails(
                    'Only Linked Location',
                    'Only Linked Publisher',
                    '',
                    '',
                    '',
                    '',
                ),
            ],
            $this->getDriver()->getPublicationDetails()
        );
    }

    /**
     * Test getAbbreviatedTitle
     *
     * @return void
     */
    public function testgetAbbreviatedTitle()
    {
        $this->assertEquals(
            [
                'Abbr title',
            ],
            $this->getDriver()->getAbbreviatedTitle()
        );
    }

    /**
     * Test getKeyTitle
     *
     * @return void
     */
    public function testgetKetTitle()
    {
        $this->assertEquals(
            [
                'Key title',
            ],
            $this->getDriver()->getKeyTitle()
        );
    }

    /**
     * Test getCollectiveUniformTitle
     *
     * @return void
     */
    public function testgetCollectiveUniformTitle()
    {
        $this->assertEquals(
            [
                [
                    'name' => 'Collective uniform title',
                    'value' => '',
                    'link' => '',
                ],
            ],
            $this->getDriver()->getCollectiveUniformTitle()
        );
    }
}
