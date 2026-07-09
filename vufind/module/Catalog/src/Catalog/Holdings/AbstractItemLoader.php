<?php

/**
 * Prepares data for Holdings features.
 *
 * PHP version 8
 *
 * @category VuFind
 * @package  Holdings
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/vufind/ Main page
 */

namespace Catalog\Holdings;

use Catalog\Utils\RegexLookup as Regex;
use Catalog\View\Helper\Root\Record as Record;
use VuFind\RecordDriver\AbstractBase as RecordDriver;

use function array_key_exists;
use function count;
use function is_array;

/**
 * Prepares data for Holdings features.
 *
 * PHP version 8
 *
 * @phpstan-consistent-constructor
 *
 * @category VuFind
 * @package  Holdings
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/vufind/ Main page
 */
abstract class AbstractItemLoader
{
    public $driver;  // record driver

    public $record;  // record view helper

    public $items;   // holding items

    public $item_id; // current item

    public $item;   // holding item for set item_id

    protected $configReader; // Config Plugin Manager to read config files

    /**
     * Initializes the loader with the given record and item data
     *
     * @param RecordDriver  $driver       Record driver
     * @param Record        $record       Record view helper
     * @param object|array  $items        array of holding items
     * @param string        $item_id      holding record data for the current holding item
     * @param PluginManager $configReader Config reader object
     */
    public function __construct($driver, $record, $items, $item_id = null, $configReader = null)
    {
        $this->driver = $driver;
        $this->record = $record;
        $this->items = $items;
        $this->configReader = $configReader;
        $this->item_id = $item_id;

        if (null !== $this->item_id) {
            $this->item = $this->getItem($this->item_id);
        }
        foreach ($this->items as &$item) {
            $this->record->updateAvailabilityStatus($item);
        }
    }

    /**
     * Instantiate class pulling driver and record from current view
     *
     * @param object $view The view to retrieve the driver from
     *
     * @return static|null
     */
    public static function fromView($view)
    {
        try {
            $container = $view->getHelperPluginManager()->getServiceLocator();
            $configReader = $container->get(\VuFind\Config\PluginManager::class);
            $record = $view->record($view->driver);
            $rtHoldings = $view->driver->getRealTimeHoldings();
            $holdings = $rtHoldings['holdings'] ?? [];

            $items = [];
            foreach ($holdings as $item_arr) {
                if (isset($item_arr['items'])) {
                    $items = array_merge($items, $item_arr['items']);
                }
            }

            // 'new static' triggers Late Static Binding.
            // It calls the constructor of the class that called fromView().
            return new static($record, $items, null, $configReader);
        } catch (\Throwable $e) {
            // Log error here if needed: error_log($e->getMessage());
            return null;
        }
    }

    /**
     *  Get the msul.ini config
     *
     * @return ConfigReader
     */
    protected function getMsulConfig()
    {
        return $this->configReader->get('msul');
    }

    /**
     * Returns if the current record is an HLM record or not
     *
     * @return bool  Depending on if the current record has the hlm prefix or not
     */
    public function isHLM()
    {
        return str_starts_with($this->driver->getUniqueId(), 'hlm.');
    }

    /**
     * Logic used to determine which item id to use
     *
     * @param string $item_id The holding item UUID.
     *
     * @return string $item_id for the selected item
     */
    protected function getItemId($item_id = null)
    {
        if (null !== $item_id) {
            return $item_id; // use the one passed as a parameter first
        } elseif (null !== $this->item_id) {
            return $this->item_id; // get the one set by the loader
        } elseif (count($this->items) > 0) {
            return $this->items[0]['item_id']; // grab the first holding record
        } else {
            return null; // This shouldn't happen, but we have no item id!
        }
    }

    /**
     * Get the holding record for the given item id. If none is provided, the first holding
     * record will be returned.
     *
     * @param string $item_id The holding item UUID. If null (default) will return for what
     *                        is set in the class if available, else the first item
     *
     * @return array The data for with the holding information of the given item
     */
    public function getItem($item_id = null)
    {
        $item_id = $this->getItemId($item_id);
        foreach ($this->items as $item) {
            if ($item_id === null || $item['item_id'] == $item_id) {
                return $item;
            }
        }
        return null;
    }

    /**
     * Get the location for a holding item
     *
     * @param string $item_id The holding item UUID. If null (default) will return status for first item
     *
     * @return string The location string
     */
    public function getLocation($item_id = null)
    {
        return $this->getItem($item_id)['location'] ?? '';
    }

    /**
     * Get the location code for a holding item
     *
     * @param string $item_id The holding item UUID. If null (default) will return status for first item
     *
     * @return string The location code
     */
    public function getLocationCode($item_id = null)
    {
        return $this->getItem($item_id)['location_code'] ?? '';
    }

    /**
     * Get the link data for requesting the item
     *
     * @param string $item_id The holding item UUID. If null (default) will return status for first item
     *
     * @return array|string The data required to build a request URL for the item
     */
    public function getLink($item_id = null)
    {
        $item_id = $this->getItemId($item_id);
        $linkdata = ['link' => ''];

        // If $item_id is null, call getItem just in case $items returns the items in a different order
        // than the real time holdings information
        $item_id = $this->getItem($item_id)['item_id'] ?? null;

        $holdings = $this->driver->getRealTimeHoldings();
        if (array_key_exists('holdings', $holdings)) {
            foreach ($holdings['holdings'] as $location) {
                if (array_key_exists('items', $location)) {
                    foreach ((array)$location['items'] as $item) {
                        if ($item_id === null || (array_key_exists('item_id', $item) && $item['item_id'] == $item_id)) {
                            $linkdata = $item;
                            break;
                        }
                    }
                }
            }
        }
        return array_key_exists('link', $linkdata) ? $linkdata['link'] : '';
    }

    /**
     * Get the FOLIO status for a holding or item
     *
     * @param string $item_id The item UUID. If null (default) will return status for first item
     *
     * @return string The FOLIO status string
     */
    public function getStatus($item_id = null)
    {
        $item_id = $this->getItemId($item_id);
        $item = $this->getItem($item_id);
        return $item['status'];
    }

    /**
     * Get the status description for a holding or item, for display only
     *
     * @param string $item_id The holding item UUID. If null (default) will return status for first item
     *
     * @return string The status description string
     */
    public function getStatusDescription($item_id = null)
    {
        $item_id = $this->getItemId($item_id);
        $item = $this->getItem($item_id);
        return $item['availability']->getStatusDescription();
    }

    /**
     * Get the call number for the record
     *
     * @param string $item_id Item to filter the result for
     *
     * @return string The description string
     */
    public function getCallNumber($item_id = null)
    {
        $item_id = $this->getItemId($item_id);
        $item = $this->getItem($item_id);

        $callnum = '';
        if ($item['callnumber'] ?? false) {
            $callnum .= ($item['callnumber_prefix'] ? $item['callnumber_prefix'] . ' ' : '') .
                        $item['callnumber'];
        }

        if ($item['enumchron'] ?? false) {
            $callnum .= ' ' . $item['enumchron'];
        }

        if ($item != null && isset($item['number']) && $item['number'] > 1) {
            $callnum .= ' (Copy #' . ($item['number']) . ')';
        }

        if ($this->isOnlineResource($item_id)) {
            $callnum = 'Online';
        }

        if (empty($callnum)) {
            $callnum = 'Access';
        }

        return $callnum;
    }

    /**
     * Get the description for the record
     *
     * @return string The description string
     */
    public function getDescription()
    {
        $results = [];
        // TODO how to get actual description?
        // Does appear to work on items that show a description on record page:
        // https://devel-getthis.aws.lib.msu.edu/Record/folio.in00006771086
        // (then var_dump this desc and the value matches)
        $data = $this->driver->getSummary();

        // If there is linked data in the description, it will need to
        // be parsed out with an '=' separating it
        if (isset($data) && isset($data[0]) && is_array($data[0])) {
            foreach ($data as $item) {
                if ($item['link']) {
                    $results[] = ($item['note'] ?? '') . ' = ' . ($item['link'] ?? '');
                } else {
                    $results[] = $item['note'] ?? '';
                }
            }
        } else {
            $results = $data;
        }
        return implode(', ', $results);
    }

    /**
     * Get the ArcGIS floor ID for a holding item
     *
     * @param string $item_id The holding item UUID. If null (default) will return status for first item
     *
     * @return string The Arc GIS floor ID string
     */
    public function getFloorId($item_id = null)
    {
        return $this->getItem($item_id)['gisfloor'] ?? '';
    }

    /**
     * Get the MSUL location note for a holding item
     *
     * @param string $item_id The holding item UUID. If null (default) will return status for first item
     *
     * @return string The Arc GIS floor ID string
     */
    public function getMsulLocation($item_id = null)
    {
        return $this->getItem($item_id)['msulLocation'] ?? '';
    }

    /**
     * Determine if the given item is an online resource
     *
     * @param string $item_id Item ID to filter for
     *
     * @return bool  If the item is an online resource
     */
    public function isOnlineResource($item_id = null)
    {
        $item_id = $this->getItemId($item_id);
        $loc = $this->getLocation($item_id);
        return Regex::ONLINE($loc);
    }
}
