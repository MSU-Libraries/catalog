<?php

/**
 * Prepares data for the Get This button
 *
 * PHP version 7
 *
 * @category VuFind
 * @package  Get_This
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/vufind/ Main page
 */

namespace Catalog\GetThis;

use Catalog\GetThis\RegexLookup as Regex;

/**
 * Class to hold data for the Get This button
 *
 * @category VuFind
 * @package  Backend_EDS
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/vufind/ Main page
 */
class GetThisLoader
{
    public $record;  // record driver

    public $items;   // holding items

    public $item_id; // current item

    public $item;   // holding item for set item_id

    public $msgTemplate; // template to use for servMsg

    /**
     * Initializes the loader with the given record and item data
     *
     * @param object $record  Record driver object
     * @param object $items   array of holding items
     * @param string $item_id holding record data for the current holding item
     */
    public function __construct($record, $items, $item_id = null)
    {
        $this->record = $record;
        $this->items = array_values(array_filter($items, function ($it) {
            return !GetThisLoader::locationAtLoM($it);
        }));
        $this->item_id = $item_id;
        $this->msgTemplate = null;
        if (null !== $this->item_id) {
            $this->item = $this->getItem($this->item_id);
        }
    }

    /**
     * Instantiate class pulling driver and record from current view
     *
     * @param object $view The view to retrieve the driver from
     *
     * @return GetThisLoader|null
     */
    public static function fromView($view)
    {
        $getthis = null;
        try {
            $record = $view->record($view->driver);
            $holdings = $view->driver->getRealTimeHoldings()['holdings'];
            $items = [];
            foreach ($holdings as $key => $item_arr) {
                $items = array_merge($items, $item_arr['items']);
            }
            $getthis = new \Catalog\GetThis\GetThisLoader($record, $items);
        } catch (\Throwable $e) {
            // Allow empty getthis when ILS is unavailable
        }
        return $getthis;
    }

    /**
     * Returns if the current record is an HLM record or not
     *
     * @return bool  Depending on if the current record has the hlm prefix or not
     */
    public function isHLM()
    {
        return str_starts_with($this->record->getUniqueId(), "hlm.");
    }

    /**
     * Logic used to determine which item id to use
     *
     * @param string $item_id The holding item UUID.
     *
     * @return string $item_id for the selected item
     */
    private function getItemId($item_id = null)
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
        $item = null;
        foreach ($this->items as $hold_item) {
            if ($item_id === null || $hold_item['item_id'] == $item_id) {
                $item = $hold_item;
                break;
            }
        }
        return $item;
    }

    /**
     * Get the status for a holding item
     *
     * @param string $item_id The holding item UUID. If null (default) will return status for first item
     *
     * @return string The status string
     */
    public function getStatus($item_id = null)
    {
        $item_id = $this->getItemId($item_id);
        $status = $this->getItem($item_id)['status'] ?? "Unknown";

        if (
            in_array($status, ['Aged to lost', 'Claimed returned', 'Declared lost', 'In process',
            'In process (non-requestable)', 'Long missing', 'Lost and paid', 'Missing', 'On order', 'Order closed',
            'Unknown', 'Withdrawn'])
        ) {
            $status = 'Unavailable';
        } elseif (
            in_array(
                $status,
                ['Awaiting pickup', 'Awaiting delivery', 'In transit', 'Paged', 'Checked out']
            )
        ) {
            $status = 'Checked Out';
        } elseif ($status == 'Restricted') {
            $status = 'Library Use Only';
        } elseif (!in_array($status, ['Available', 'Unavailable'])) {
            $status = 'Unknown status';
        }

        return $status;
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
        $item_id = $this->getItemId($item_id);
        return $this->getItem($item_id)['location'] ?? "";
    }

    /**
     * Get the link data for requesting the item
     *
     * @param string $item_id The holding item UUID. If null (default) will return status for first item
     *
     * @return array The data required to build a request URL for the item
     */
    public function getLink($item_id = null)
    {
        $item_id = $this->getItemId($item_id);
        $linkdata = ['link' => ''];

        // If $item_id is null, call getItem just in case $items returns the items in a different order
        // than the real time holdings information
        $item_id = $this->getItem($item_id)['item_id'] ?? null;

        $holdings = $this->record->getRealTimeHoldings();
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

        $callnum = "";
        if ($item['callnumber'] ?? false) {
            $callnum .= ($item['callnumber_prefix'] ? $item['callnumber_prefix'] . ' ' : '') .
                        $item['callnumber'];
        }

        if ($item['enumchron'] ?? false) {
            $callnum .= " " . $item['enumchron'];
        }

        if ($item != null && isset($item['number']) && $item['number'] > 1) {
            $callnum .= " (Copy #" . ($item['number']) . ")";
        }

        if ($this->isOnlineResource($item_id)) {
            $callnum = "Online";
        }

        if (empty($callnum)) {
            $callnum = "Access";
        }

        return $callnum;
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

    /**
     * Return true if the location of the given item is at Library of Michigan
     *
     * @param array $item item record
     *
     * @return bool
     */
    public static function locationAtLoM($item)
    {
        return str_starts_with($item['location'] ?? '', 'Library of Michigan');
    }

    /**
     * Get the description for the record
     *
     * @return string The description string
     */
    public function getDescription()
    {
        // TODO how to get actual description?
        // Does appear to work on items that show a description on record page:
        // https://devel-getthis.aws.lib.msu.edu/Record/folio.in00006771086
        // (then var_dump this desc and the value matches)
        return implode(', ', $this->record->getSummary());
    }

    /**
     * Determine if the given item is a serial or not
     *
     * @param string $item_id Item ID to filter for
     *
     * @return bool  If the item is a serial or not
     */
    public function isSerial($item_id = null)
    {
        $item_id = $this->getItemId($item_id);
        $is_serial = false;
        foreach ($this->record->getFormats() as $format) {
            if (preg_match('/SERIAL/i', $format)) {
                $is_serial = true;
            }
        }
        return $is_serial;
    }

    /**
     * Determine if the given item is checked or not
     *
     * @param string $item_id Item ID to filter for
     *
     * @return bool  If the item is out or not
     */
    public function isOut($item_id = null)
    {
        $item_id = $this->getItemId($item_id);
        $status = $this->getStatus($item_id);
        return
            preg_match('/CHECKED/i', $status) ||
            preg_match('/BILLED/i', $status) ||
            preg_match('/ON SEARCH/i', $status) ||
            preg_match('/LOST/i', $status) ||
            preg_match('/HOLD/i', $status)
        ;
    }

    /**
     * Determine if the given item is a media item or not
     *
     * @param string $item_id Item ID to filter for
     *
     * @return bool  If the item is a media item or not
     */
    public function isMedia($item_id = null)
    {
        $item_id = $this->getItemId($item_id);
        $callNum = strtolower($this->getItem($item_id)['callnumber'] ?? "");
        return
            preg_match('/fiche/', $callNum) ||
            preg_match('/disc/', $callNum) ||
            preg_match('/video/', $callNum) ||
            preg_match('/cd/', $callNum) ||
            preg_match('/dvd/', $callNum)
        ;
    }

    /**
     * Determine if the given item is for library use only or not
     *
     * @param string $item_id Item ID to filter for
     *
     * @return bool  If the item is for library use only or not
     */
    public function isLibUseOnly($item_id = null)
    {
        $item_id = $this->getItemId($item_id);
        $stat = $this->getStatus($item_id);
        return Regex::LIB_USE_ONLY($stat);
    }

    /**
     * Determine if the given item is unavailable
     *
     * @param string $item_id Item ID to filter for
     *
     * @return bool  If the item is unavailable
     */
    public function isUnavailable($item_id = null)
    {
        $item_id = $this->getItemId($item_id);
        $stat = $this->getStatus($item_id);
        return $stat == 'Unavailable';
    }

    /**
     * Determine if we should show the in process form
     *
     * @param string $item_id Item ID to filter for
     *
     * @return bool  If the item is a serial or not
     */
    public function showInProcess($item_id = null)
    {
        //$stat = $this->getStatus($item_id);
        //return Regex::IN_PROCESS($stat);
        // XXX Not implementing this form for now
        return false;
    }

    /**
     * Determine if the message forms should display
     *
     * @param string $item_id Item ID to filter for
     *
     * @return string which template to display
     */
    public function showServMsg($item_id = null)
    {
        $item_id = $this->getItemId($item_id);
        $stat = $this->getStatus($item_id);
        $loc = $this->getLocation($item_id);

        if ($this->isOut($item_id)) {
            if (Regex::MAKERSPACE($loc)) {
                $this->msgTemplate = 'makercheckedout.phtml';
            }
        } else {
            if (Regex::ART($loc) && Regex::PERM($loc)) {
                $this->msgTemplate = 'reserve.phtml';
            } elseif (Regex::ART($loc) || Regex::REFERENCE($loc)) {
                $this->msgTemplate = 'ask.phtml';
            } elseif (Regex::RESERVE_DIGITAL($loc)) {
                if (Regex::AVAILABLE($stat)) {
                    $this->msgTemplate = 'cdlavail.phtml';
                } else {
                    $this->msgTemplate = 'cdlout.phtml';
                }
            } elseif (Regex::DIGITAL_MEDIA($loc) || (Regex::MUSIC($loc) && Regex::REF($loc))) {
                $this->msgTemplate = 'pickup.phtml';
            } elseif (Regex::VIDEO_GAME($loc)) {
                $this->msgTemplate = 'game.phtml';
            } elseif (Regex::LAW_RESERVE($loc)) {
                $this->msgTemplate = 'lawreserve.phtml';
            } elseif (Regex::LAW_RARE_BOOK($loc)) {
                $this->msgTemplate = 'lawrare.phtml';
            } elseif (Regex::SCHAEFER($loc) && !Regex::AVAILABLE($stat)) {
                $this->msgTemplate = 'law.phtml';
            } elseif (Regex::MAKERSPACE($loc)) {
                $this->msgTemplate = 'maker.phtml';
            } elseif (Regex::MAP($loc)) {
                if (Regex::CIRCULATING($loc) && $this->isLibUseOnly()) {
                    $this->msgTemplate = 'ask.phtml';
                } elseif (!Regex::CIRCULATING($loc)) {
                    $this->msgTemplate = 'mappickup.phtml';
                }
            } elseif (Regex::TURFGRASS($loc)) {
                $this->msgTemplate = 'turfgrass.phtml';
            } elseif (Regex::VINCENT_VOICE($loc)) {
                $this->msgTemplate = 'pickup.phtml';
            }
        }
        return $this->msgTemplate !== null;
    }

    /**
     * Determine if the request item template should display
     *
     * @param string $item_id Item ID to filter for
     *
     * @return bool  If the template should display
     */
    public function showReqItem($item_id = null)
    {
        $item_id = $this->getItemId($item_id);
        $loc = $this->getLocation($item_id);
        if (Regex::BUSINESS($loc) && !Regex::RESERV($loc)) {
            return true;
        }
        return false;
    }

    /**
     * Determine if the request scanning template should display
     *
     * @param string $item_id Item ID to filter for
     *
     * @return bool  If the template should display
     */
    public function showReqScan($item_id = null)
    {
        $item_id = $this->getItemId($item_id);
        $stat = $this->getStatus($item_id);

        if (Regex::AVAILABLE($stat)) {
            return true;
        }
        return false;
    }

    /**
     * Determine if the request business item template should display
     *
     * @param string $item_id Item ID to filter for
     *
     * @return bool  If the template should display
     */
    public function showReqBusiness($item_id = null)
    {
        $item_id = $this->getItemId($item_id);
        $loc = $this->getLocation($item_id);
        if (Regex::BUSINESS($loc) && !Regex::RESERV($loc)) {
            return true;
        }
        return false;
    }

    /**
     * Determine if the get Rovi item template should display
     *
     * @param string $item_id Item ID to filter for
     *
     * @return bool  If the template should display
     */
    public function showGetRovi($item_id = null)
    {
        //XXX Not implementing for now
        return false;
    }

    /**
     * Determine if the get locker pickup template should display
     *
     * @param string $item_id Item ID to filter for
     *
     * @return bool  If the template should display
     */
    public function showLockerPick($item_id = null)
    {
        $item_id = $this->getItemId($item_id);
        $stat = $this->getStatus($item_id);
        $loc = $this->getLocation($item_id);

        if (
            (Regex::ART($loc) && !Regex::PERM($loc) && !$this->isLibUseOnly()) ||
             (Regex::BROWSING($loc) && Regex::AVAILABLE($stat)) ||
             (Regex::CAREER($loc) && Regex::AVAILABLE($stat)) ||
             (Regex::CESAR_CHAVEZ($loc) && Regex::AVAILABLE($stat)) ||
             (Regex::KLINE_DMC($loc) && !Regex::RESERV($loc) && Regex::AVAILABLE($stat)) ||
             (Regex::FACULTY_BOOK($loc) && Regex::AVAILABLE($stat)) ||
             (Regex::GOV($loc) && Regex::AVAILABLE($stat)) ||
             (Regex::MAKERSPACE($loc) && Regex::AVAILABLE($stat)) ||
             (Regex::MAP($loc) && Regex::CIRCULATING($loc) && Regex::AVAILABLE($stat)) ||
             (Regex::MUSIC($loc) && !(Regex::REF($loc) || Regex::RESERV($loc))) ||
             (Regex::ROVI($loc)) ||
             (Regex::TRAVEL($loc) && Regex::AVAILABLE($stat)) ||
             (Regex::MAIN($loc) && Regex::AVAILABLE($stat)) ||
             (Regex::AVAILABLE($stat))
        ) {
            return true;
        }
        return false;
    }

    /**
     * Determine if the get remote item template should display
     *
     * @param string $item_id Item ID to filter for
     *
     * @return bool  If the template should display
     */
    public function showRemForm($item_id = null)
    {
        //XXX Not implementing for now
        return false;
    }

    /**
     * Determine if the faculty delivery template should display
     *
     * @param string $item_id Item ID to filter for
     *
     * @return bool  If the template should display
     */
    public function showFacDel($item_id = null)
    {
        $item_id = $this->getItemId($item_id);
        $stat = $this->getStatus($item_id);
        $loc = $this->getLocation($item_id);
        $desc = $this->getDescription();

        // Never show if the item is out, unavailable, on reserve or Remote SPC
        if (
            $this->isOut($item_id) || $this->isUnavailable($item_id) || Regex::RESERV($loc)
            || Regex::SPEC_COLL_REMOTE($loc)
        ) {
            return false;
        }

        if (
            (Regex::ART($loc) &&
            !Regex::PERM($loc) &&
            !$this->isLibUseOnly()) ||
             (Regex::BUSINESS($loc) &&
             !Regex::RESERV($loc)) ||
             (Regex::MAP($loc) &&
             Regex::CIRCULATING($loc) &&
             Regex::AVAILABLE($stat)) ||
             (Regex::MUSIC($loc) &&
             !(Regex::REF($loc) ||
             Regex::RESERV($loc))) ||
             (Regex::REMOTE($loc) &&
             !Regex::VINYL($desc) &&
              !Regex::SPEC_COLL_REMOTE($loc) &&
              !Regex::MICROFORMS($loc)) ||
             (Regex::ROVI($loc)) ||
             (Regex::THESES_REMOTE_MICRO($loc)) ||
             (Regex::MAIN($loc) &&
             Regex::AVAILABLE($stat)) ||
             (Regex::AVAILABLE($stat))
        ) {
            return true;
        }
        return false;
    }

    /**
     * Determine if the remote parton template should display
     *
     * @param string $item_id Item ID to filter for
     *
     * @return bool  If the template should display
     */
    public function showRemotePat($item_id = null)
    {
        $item_id = $this->getItemId($item_id);
        $stat = $this->getStatus($item_id);
        $loc = $this->getLocation($item_id);
        $desc = $this->getDescription();

        // Never show if the item is out, unavailable, on reserve or Remote SPC
        if (
            $this->isOut($item_id) || $this->isUnavailable($item_id) || Regex::RESERV($loc)
            || Regex::SPEC_COLL_REMOTE($loc)
        ) {
            return false;
        }

        if (
            (Regex::ART($loc) && !Regex::PERM($loc) && !$this->isLibUseOnly()) ||
             (Regex::BROWSING($loc) && Regex::AVAILABLE($stat)) ||
             (Regex::BUSINESS($loc) && !Regex::RESERV($loc)) ||
             (Regex::CAREER($loc) && Regex::AVAILABLE($stat)) ||
             (Regex::CESAR_CHAVEZ($loc) && Regex::AVAILABLE($stat)) ||
             (Regex::VIDEO_GAME($loc)) ||
             (Regex::KLINE_DMC($loc) && !Regex::RESERV($loc) && Regex::AVAILABLE($stat)) ||
             (Regex::FACULTY_BOOK($loc) && Regex::AVAILABLE($stat)) ||
             (Regex::SCHAEFER($loc) && Regex::AVAILABLE($stat)) ||
             (Regex::GOV($loc) && Regex::AVAILABLE($stat)) ||
             (Regex::MAP($loc) && Regex::CIRCULATING($loc) && Regex::AVAILABLE($stat)) ||
             (Regex::MUSIC($loc) && !(Regex::REF($loc) || Regex::RESERV($loc))) ||
             (Regex::REMOTE($loc)) && !Regex::VINYL($desc) ||
             (Regex::ROVI($loc)) ||
             (Regex::TRAVEL($loc) && Regex::AVAILABLE($stat)) ||
             (Regex::MAIN($loc) && Regex::AVAILABLE($stat)) ||
             (Regex::AVAILABLE($stat))
        ) {
            return true;
        }
        return false;
    }

    /**
     * Determine if the SPC/Aeon request template should display
     *
     * @param string $item_id Item ID to filter for
     *
     * @return bool  If the template should display
     */
    public function showSpcAeon($item_id = null)
    {
        $item_id = $this->getItemId($item_id);
        $stat = $this->getStatus($item_id);
        $loc = $this->getLocation($item_id);

        if (
            (Regex::SPEC_COLL_REMOTE($loc) && (Regex::LIB_USE_ONLY($stat) || Regex::ON_DISPLAY($stat))) ||
             Regex::SPEC_COLL($loc)
        ) {
            return true;
        }
        return false;
    }

    /**
     * Determine if the other library links template should display
     *
     * @param string $item_id Item ID to filter for
     *
     * @return bool  If the template should display
     */
    public function showOtherLib($item_id = null)
    {
        $item_id = $this->getItemId($item_id);
        $loc = $this->getLocation($item_id);

        // Never show on Remote SPC items (PC-439)
        if (Regex::SPEC_COLL_REMOTE($loc)) {
            return false;
        }

        // only if the item is on reserve, non-circulating (lib use only), checked out or unavailable
        if (
            Regex::RESERV($loc) || $this->isOut($item_id) || $this->isLibUseOnly($item_id)
            || $this->isUnavailable($item_id)
        ) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the University Archives template should display
     *
     * @param string $item_id Item ID to filter for
     *
     * @return bool  If the template should display
     */
    public function showUahc($item_id = null)
    {
        $item_id = $this->getItemId($item_id);
        // only show if any of the items in the instance are held by UAHC
        if ($item_id === null) {
            $loc = $this->getLocation($item_id);
            if (Regex::UNIV_ARCH($loc)) {
                return true;
            }
        } else {
            foreach ($this->items as $item) {
                $loc = $this->getLocation($item['item_id']);
                if (Regex::UNIV_ARCH($loc)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Determine if the microfiche template should display
     *
     * @param string $item_id Item ID to filter for
     *
     * @return bool  If the template should display
     */
    public function showMicrofiche($item_id = null)
    {
        $item_id = $this->getItemId($item_id);
        if ($item_id === null) {
            $loc = $this->getLocation($item_id);
            if (Regex::MICROFORMS($loc)) {
                return true;
            }
        } else {
            foreach ($this->items as $item) {
                $loc = $this->getLocation($item['item_id']);
                if (Regex::MICROFORMS($loc)) {
                    return true;
                }
            }
        }
        return false;
    }
}
