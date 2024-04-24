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

use Catalog\GetThis\GetThisLoader;
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
    use \Catalog\Feature\FixtureTrait;
    use \VuFindTest\Feature\ReflectionTrait;

    /**
     * Get getting an item id when null is passed
     *
     * @return null
     */
    public function testGetItemIdNullPassed()
    {
        $this->assertEquals('123', $this->callMethod($this->getHandler(), 'getItemId', []));
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
        $this->assertEquals(null, $this->callMethod($this->getHandler(), 'getItem', ['999']));
    }

    /**
     * Get getting an item status with null item passed
     *
     * @return null
     */
    public function testGetStatusNullPassed()
    {
        $this->assertEquals('Available', $this->callMethod($this->getHandler(), 'getStatus', []));
    }

    /**
     * Get getting an item status with first item passed
     *
     * @return null
     */
    public function testGetStatusAvailable()
    {
        $this->assertEquals('Available', $this->callMethod($this->getHandler(), 'getStatus', ['123']));
    }

    /**
     * Get getting an item status with a missing item
     *
     * @return null
     */
    public function testGetStatusUnavailable()
    {
        $this->assertEquals('Unavailable (Missing)', $this->callMethod($this->getHandler(), 'getStatus', ['456']));
    }

    /**
     * Get getting an item status with a checked out item
     *
     * @return null
     */
    public function testGetStatusCheckedOut()
    {
        $this->assertEquals('Checked Out (In transit)', $this->callMethod($this->getHandler(), 'getStatus', ['789']));
    }

    /**
     * Get getting an item status with a restricted item
     *
     * @return null
     */
    public function testGetStatusRestricted()
    {
        $this->assertEquals('Library Use Only', $this->callMethod($this->getHandler(), 'getStatus', ['321']));
    }

    /**
     * Get getting an item status with a reserve item
     *
     * @return null
     */
    public function testGetStatusReserve()
    {
        $this->assertEquals('On Reserve', $this->callMethod($this->getHandler(), 'getStatus', ['654']));
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
        return new GetThisLoader($this->getDriver(), $this->getItems(), $itemId);
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
                'status' => 'Available',
                'reserve' => 'N',
            ],
            [
                'item_id' => '456',
                'status' => 'Missing',
            ],
            [
                'item_id' => '789',
                'status' => 'In transit',
            ],
            [
                'item_id' => '321',
                'status' => 'Restricted',
            ],
            [
                'item_id' => '654',
                'status' => 'Available',
                'reserve' => 'Y',
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
        $driver = $this->createMock(\VuFind\RecordDriver\AbstractBase::class);
        $driver->expects($this->any())->method('getUniqueId')
            ->will($this->returnValue($id));
        $driver->expects($this->any())->method('getSourceIdentifier')
            ->will($this->returnValue($source));
        return $driver;
    }
}
