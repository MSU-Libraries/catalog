<?php

/**
 * Get This loader Test Class
 *
 * PHP version 8
 *
 * @category VuFind
 * @package  Catalog
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 **/

namespace CatalogTest\GetThis;

use Catalog\Holdings\GetThisLoader;
use VuFind\ILS\Logic\AvailabilityStatus;
use VuFind\RecordDriver\AbstractBase as RecordDriver;

/**
 * Get This loader Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

class GetThisLoaderTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;
    use \VuFindTest\Feature\ReflectionTrait;

    /**
     * Test isHLM
     *
     * @return null
     */
    public function testIsHLM()
    {
        $this->assertFalse($this->callMethod($this->getHandler(), 'isHLM', []));
    }

    /**
     * Test getLocation
     *
     * @return null
     */
    public function testGetLocationEmpty()
    {
        $this->assertEquals('', $this->getHandler()->getLocation());
    }

    /**
     * Test getLocation
     *
     * @return null
     */
    public function testGetLocationSet()
    {
        $this->assertEquals('Main Library', $this->getHandler()->getLocation('456'));
    }

    /**
     * Test getLocationCode
     *
     * @return null
     */
    public function testGetLocationCode()
    {
        $this->assertEquals('ML', $this->getHandler()->getLocationCode('456'));
    }

    /**
     * Test isOnlineResource
     *
     * @return null
     */
    public function testIsOnlineResource()
    {
        $this->assertFalse($this->getHandler()->isOnlineResource('456'));
    }

    /**
     * Test isMakerspace
     *
     * @return null
     */
    public function testIsMakerspace()
    {
        $this->assertFalse($this->getHandler()->isMakerspace());
    }

    /**
     * Test getDescription
     *
     * @return null
     */
    public function testGetDescription()
    {
        $this->assertEquals('Solr', $this->getHandler()->getDescription());
    }

    /**
     * Test isOut
     *
     * @return null
     */
    public function testIsOut()
    {
        $this->assertTrue($this->getHandler()->isOut('789'));
        $this->assertFalse($this->getHandler()->isOut('123'));
    }

    /**
     * Get getting an item id when no items are added to the object
     *
     * @return null
     */
    public function testGetItemIdNoItems()
    {
        $handler = $this->getHandler(null);
        $this->setProperty($handler, 'items', []);
        $this->assertEquals(null, $this->callMethod($handler, 'getItemId', []));
    }

    /**
     * Get getting an item id when null is passed
     *
     * @return null
     */
    public function testGetItemIdNullPassed()
    {
        $this->assertEquals('123', $this->callMethod($this->getHandler(null), 'getItemId', []));
    }

    /**
     * Get getting an item id when first item id is passed
     *
     * @return null
     */
    public function testGetItemIdFirstPassed()
    {
        $this->assertEquals('123', $this->callMethod($this->getHandler(), 'getItemId', ['123']));
    }

    /**
     * Get getting an item id when second item id is passed
     *
     * @return null
     */
    public function testGetItemIdSecondPassed()
    {
        $this->assertEquals('456', $this->callMethod($this->getHandler(), 'getItemId', ['456']));
    }

    /**
     * Get getting an item id when item id is not present
     *
     * @return null
     */
    public function testGetItemIdMissing()
    {
        // Still returns the invalid id since getItem handles loopining all the items
        $this->assertEquals('999', $this->callMethod($this->getHandler(), 'getItemId', ['999']));
    }

    /**
     * Get getting an item when none is passed
     *
     * @return null
     */
    public function testGetItemNullPassed()
    {
        $this->assertEquals($this->getItems()[0], $this->callMethod($this->getHandler(), 'getItem', []));
    }

    /**
     * Get getting an item when the first is passed
     *
     * @return null
     */
    public function testGetItemFirstPassed()
    {
        $this->assertEquals($this->getItems()[0], $this->callMethod($this->getHandler(), 'getItem', ['123']));
    }

    /**
     * Get getting an item when second item id is passed
     *
     * @return null
     */
    public function testGetItemSecondPassed()
    {
        $this->assertEquals($this->getItems()[1], $this->callMethod($this->getHandler(), 'getItem', ['456']));
    }

    /**
     * Get getting an item when an invalid item id passed
     *
     * @return null
     */
    public function testGetItemInvalidId()
    {
        $this->assertEquals(null, $this->callMethod($this->getHandler(), 'getItem', ['0000']));
    }

    /**
     * Get getting an item status description with null item passed
     *
     * @return null
     */
    public function testGetStatusNullPassed()
    {
        $this->assertEquals('Available (test)', $this->callMethod($this->getHandler(), 'getStatusDescription', []));
    }

    /**
     * Get getting an item status description with first item passed
     *
     * @return null
     */
    public function testGetStatusAvailable()
    {
        $this->assertEquals(
            'Available (test)',
            $this->callMethod($this->getHandler(), 'getStatusDescription', ['123'])
        );
    }

    /**
     * Get getting an item status description with a missing item
     *
     * @return null
     */
    public function testGetStatusUnavailable()
    {
        $this->assertEquals(
            'Unavailable (Missing)',
            $this->callMethod($this->getHandler(), 'getStatusDescription', ['456'])
        );
    }

    /**
     * Test getStatusDescription with unknown status
     *
     * @return null
     */
    public function testGetStatusUnknown()
    {
        $this->assertEquals(
            'Unknown status (test)',
            $this->callMethod($this->getHandler(), 'getStatusDescription', ['999'])
        );
    }

    /**
     * Get getting an item status description with a checked out item
     *
     * @return null
     */
    public function testGetStatusCheckedOut()
    {
        $this->assertEquals(
            'Checked Out (In transit) - 1/1/2000',
            $this->callMethod($this->getHandler(), 'getStatusDescription', ['789'])
        );
    }

    /**
     * Get getting an item status description with a restricted item
     *
     * @return null
     */
    public function testGetStatusRestricted()
    {
        $this->assertEquals(
            'Library Use Only',
            $this->callMethod($this->getHandler(), 'getStatusDescription', ['321'])
        );
    }

    /**
     * Get getting an item status description with a reserve item
     *
     * @return null
     */
    public function testGetStatusReserve()
    {
        $this->assertEquals('On Reserve', $this->callMethod($this->getHandler(), 'getStatusDescription', ['654']));
    }

    /**
     * Get getting status description of a checked out item
     *
     * @return null
     */
    public function testGetStatusCheckedOutDueDate()
    {
        $this->assertEquals(
            'Checked Out (In transit) - Due: 12/12/2000',
            $this->callMethod($this->getHandler(), 'getStatusDescription', ['012'])
        );
    }

    /**
     * Get test GetThisLoader object
     *
     * @param string $itemId Item Id
     *
     * @return GetThisLoader
     */
    protected function getHandler($itemId = '123')
    {
        return new GetThisLoader($this->getDriver(), $this->getRecordViewHelper(), $this->getItems(), $itemId);
    }

    /**
     * Get test items for testing against
     *
     * @return Array
     */
    protected function getItems()
    {
        return [
            [
                'item_id' => '123',
                'availability' => new AvailabilityStatus(true, 'Available (test)'),
                'status' => 'Available',
                'reserve' => 'N',
                'loan_type_name' => 'test',
            ],
            [
                'item_id' => '456',
                'availability' => new AvailabilityStatus(false, 'Unavailable (Missing)'),
                'status' => 'Missing',
                'location' => 'Main Library',
                'location_code' => 'ML',
            ],
            [
                'item_id' => '789',
                'availability' => new AvailabilityStatus(false, 'Checked Out (In transit) - 1/1/2000'),
                'status' => 'In transit',
                'returnDate' => '1/1/2000',
            ],
            [
                'item_id' => '321',
                'availability' => new AvailabilityStatus(false, 'Library Use Only'),
                'status' => 'Restricted',
            ],
            [
                'item_id' => '654',
                'availability' => new AvailabilityStatus(false, 'On Reserve'),
                'status' => 'Available',
                'reserve' => 'Y',
            ],
            [
                'item_id' => '999',
                'availability' => new AvailabilityStatus(false, 'Unknown status (test)'),
                'status' => 'test',
            ],
            [
                'item_id' => '012',
                'availability' => new AvailabilityStatus(false, 'Checked Out (In transit) - Due: 12/12/2000'),
                'status' => 'In transit',
                'duedate' => '12/12/2000',
            ],
        ];
    }

    /**
     * Get test record driver object
     *
     * @param string $id     Record ID
     * @param string $source Record source
     *
     * @return RecordDriver
     */
    protected function getDriver($id = 'test', $source = 'Solr')
    {
        // $driver = $this->createMock(\VuFind\RecordDriver\AbstractBase::class);
        $driver = $this->createMock(\Catalog\RecordDriver\SolrDefault::class);
        $driver->expects($this->any())->method('getUniqueId')
            ->willReturn($id);
        $driver->expects($this->any())->method('getSourceIdentifier')
            ->willReturn($source);
        $driver->expects($this->any())->method('getSummary')
            ->willReturn([$source]);
        $driver->expects($this->any())->method('getFormats')
            ->willReturn(['Serial', 'Book']);
        return $driver;
    }

    /**
     * Get test record view helper
     *
     * @return \Catalog\View\Helper\Root\Record
     */
    protected function getRecordViewHelper()
    {
        $record = $this->createMock(\Catalog\View\Helper\Root\Record::class);
        $record->expects($this->any())->method('updateAvailabilityStatus');
        return $record;
    }
}
