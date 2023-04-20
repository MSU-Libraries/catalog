<?php
namespace Catalog\GetThis;
use Catalog\GetThis\RegexLookup as Regex;

class GetThisLoader {
    public $record;  // record driver
    public $items;   // holding items
    public $item_id; // current item
    public $item;   // holding item for set item_id
    public $msgTemplate; // template to use for servMsg

    function __construct($record, $items, $item_id=null) {
        $this->record = $record;
        $this->items = $items;
        $this->item_id = $item_id;
        $this->msgTemplate = null;
        if (!is_null($this->item_id)) {
            $this->item = $this->getItem($this->item_id);
        }
    }

    public function isHLM() {
        return str_starts_with($this->record->getUniqueId(), "hlm.");
    }

    /**
     * Logic used to determine which item id to use
     *
     * @param string $item_id   The holding item UUID.
     * 
     * @param string $item_id   The holding item UUID.
     */
    private function getItemId($item_id=null) {
        if (!is_null($item_id)) return $item_id; # use the one passed as a parameter first
        elseif (!is_null($this->item_id)) return $this->item_id; # get the one set by the loader
        elseif (count($this->items) > 0) return $this->items[0]['item_id']; # grab the first holding record
        else return null; # This shouldn't happen, but we have no item id!
    }

    /**
     * Get the holding record for the given item id. If none is provided, the first holding
     * record will be returned.
     * 
     * @param string $item_id   The holding item UUID. If null (default) will return for what is set
     *                          in the class if available, else the first item
     * 
     * @return array The data for with the holding information of the given item
     */
    public function getItem($item_id=null) {
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
     * @param string $item_id   The holding item UUID. If null (default) will return status for first item
     *
     * @return string The status string
     */
    public function getStatus($item_id=null) {
        $item_id = $this->getItemId($item_id);
        $status = $this->getItem($item_id)['status'] ?? "Unknown";

        if (in_array($status, array('Aged to lost', 'Claimed returned', 'Declared lost', 'In process (non-requestable)',
            'Long missing', 'Lost and paid', 'Missing', 'On order', 'Order closed', 'Unknown', 'Withdrawn')))
          $status = 'Unavailable';
        else if (in_array($status, array('Awaiting pickup', 'Awaiting delivery', 'In transit', 'Paged', 'Checked out')))
          $status = 'Checked Out';
        else if ($status == 'Restricted')
          $status = 'Library Use Only';
        else if (!in_array($status, array('Available', 'Unavailable')))
          $status = 'Unknown status';

        return $status;
    }

    /**
     * Get the location for a holding item
     *
     * @param string $item_id   The holding item UUID. If null (default) will return status for first item
     *
     * @return string The location string
     */
    public function getLocation($item_id=null) {
        $item_id = $this->getItemId($item_id);
        return $this->getItem($item_id)['location'] ?? "";
    }

    /**
     * Get the link data for requesting the item
     *
     * @param string $item_id   The holding item UUID. If null (default) will return status for first item
     *
     * @return array The data required to build a request URL for the item
     */
    public function getLink($item_id=null) {
        $item_id = $this->getItemId($item_id);
        $linkdata = ['link' => ''];

        # If $item_id is null, call getItem just in case $items returns the items in a different order
        # than the real time holdings information
        $item_id = $this->getItem($item_id)['item_id'] ?? null;

        $holdings = $this->record->getRealTimeHoldings();
        if (array_key_exists('holdings', $holdings)) {
            foreach ($holdings['holdings'] as $location) {
                if (array_key_exists('items', $location)) {
                    foreach ((array) $location['items'] as $item) {
                        if ($item_id === null || (array_key_exists('item_id', $item) && $item['item_id'] == $item_id)) {
                            $linkdata = $item;
                            break;
                        }
                    }
                }
            }
        }
        return $linkdata['link'];
    }

    /**
     * Get the description for the record
     *
     * @return string The description string
     */
    public function getDescription() {
        // TODO how to get actual description?
        // Does appear to work on items that show a description on record page:
        // https://devel-getthis.aws.lib.msu.edu/Record/folio.in00006771086 (then var_dump this desc and the value matches)
        return implode(', ', $this->record->getSummary());
    }

    public function isSerial($item_id=null) {
        $item_id = $this->getItemId($item_id);
        $is_serial = false;
        foreach ($this->record->getFormats() as $format){
            if (preg_match('/SERIAL/i', $format)) $is_serial = true;
        }
        return $is_serial;
    }

    public function isOut($item_id=null) {
        $item_id = $this->getItemId($item_id);
        $status = $this->getStatus($item_id);
        return (
            preg_match('/CHECKED/i', $status) ||
            preg_match('/BILLED/i', $status) ||
            preg_match('/ON SEARCH/i', $status) ||
            preg_match('/LOST/i', $status) ||
            preg_match('/HOLD/i', $status)
        );
    }

    public function isMedia($item_id=null) {
        $item_id = $this->getItemId($item_id);
        $callNum = strtolower($this->getItem($item_id)['callnumber'] ?? "");
        return (
            preg_match('/fiche/', $callNum) ||
            preg_match('/disc/', $callNum) ||
            preg_match('/video/', $callNum) ||
            preg_match('/cd/', $callNum) ||
            preg_match('/dvd/', $callNum)
        );
    }

    public function isLibUseOnly($item_id=null) {
        $item_id = $this->getItemId($item_id);
        $stat = $this->getStatus($item_id);
        return Regex::LIB_USE_ONLY($stat);
    }

    public function isAllReserve() {
        $count_reserve = 0;
        foreach ($this->items as $item) {
            $loc = $this->getLocation($item['item_id']);
            if (Regex::LIB_OF_MICH($loc)) {
                continue;
            }

            if (Regex::RESERV($loc)) {
                $count_reserve += 1;
            }
        }
        if ($count_reserve == count($this->items)) {
            return false;
        }
        else return true;
    }

    /* Check if all items are on either: on reserve, non-circulating, or checked out
     *
     */
    public function isAllReserveNonCircOut() {
        $count_out = 0;

        foreach ($this->items as $item) {
            $loc = $this->getLocation($item['item_id']);
            if (Regex::LIB_OF_MICH($loc)) {
                continue;
            }

            if (Regex::RESERV($loc) || $this->isOut($item['item_id']) || $this->isLibUseOnly($item['item_id'])) {
                $count_out += 1;
            }
        }
        if ($count_out == count($this->items)) {
            return true;
        }
        else return false;
    }

    public function showInProcess($item_id=null) {
        //$stat = $this->getStatus($item_id);
        //return Regex::IN_PROCESS($stat);
        // XXX Not implementing this form for now
        return false;
    }

    public function showServMsg($item_id=null) {
        $item_id = $this->getItemId($item_id);
        $stat = $this->getStatus($item_id);
        $loc = $this->getLocation($item_id);

        if ($this->isOut($item_id)) {
            if (Regex::MAKERSPACE($loc)) {
                $this->msgTemplate = 'makercheckedout.phtml';
            }
        }
        else {
            if (Regex::ART($loc) && Regex::PERM($loc)) {
                $this->msgTemplate = 'reserve.phtml';
            }
            elseif (Regex::ART($loc) || Regex::REFERENCE($loc)) {
                $this->msgTemplate = 'ask.phtml';
            }
            elseif (Regex::RESERVE_DIGITAL($loc)) {
                if (Regex::AVAILABLE($stat)) {
                    $this->msgTemplate = 'cdlavail.phtml';
                }
                else {
                    $this->msgTemplate = 'cdlout.phtml';
                }
            }
            elseif (Regex::DIGITAL_MEDIA($loc) || (Regex::MUSIC($loc) && Regex::REF($loc))) {
                $this->msgTemplate = 'pickup.phtml';
            }
            elseif (Regex::VIDEO_GAME($loc)) {
                $this->msgTemplate = 'game.phtml';
            }
            elseif (Regex::LAW_RESERVE($loc)) {
                $this->msgTemplate = 'lawreserve.phtml';
            }
            elseif (Regex::LAW_RARE_BOOK($loc)) {
                $this->msgTemplate = 'lawrare.phtml';
            }
            elseif (Regex::SCHAEFER($loc) && !Regex::AVAILABLE($stat)) {
                $this->msgTemplate = 'law.phtml';
            }
            elseif (Regex::MAKERSPACE($loc)) {
                $this->msgTemplate = 'maker.phtml';
            }
            elseif (Regex::MAP($loc)) {
                if (Regex::CIRCULATING($loc) && $this->isLibUseOnly()) {
                    $this->msgTemplate = 'ask.phtml';
                }
                elseif (!Regex::CIRCULATING($loc)) {
                    $this->msgTemplate = 'mappickup.phtml';
                }
            }
            elseif (Regex::TURFGRASS($loc)) {
                $this->msgTemplate = 'turfgrass.phtml';
            }
            elseif (Regex::VINCENT_VOICE($loc)) {
                $this->msgTemplate = 'pickup.phtml';
            }
        }
        return $this->msgTemplate !== null;
    }

    public function showReqItem($item_id=null) {
        $item_id = $this->getItemId($item_id);
        $loc = $this->getLocation($item_id);
        if (Regex::BUSINESS($loc) && !Regex::RESERV($loc)) {
            return true;
        }
        return false;
    }

    public function showReqBusiness($item_id=null) {
        $item_id = $this->getItemId($item_id);
        $loc = $this->getLocation($item_id);
        if (Regex::BUSINESS($loc) && !Regex::RESERV($LOC)) {
            return true;
        }
        return false;
    }

    public function showGetRovi($item_id=null) {
        //XXX Not implementing for now
        return false;
    }

    public function showLockerPick($item_id=null) {
        $item_id = $this->getItemId($item_id);
        $stat = $this->getStatus($item_id);
        $loc = $this->getLocation($item_id);

        if ( (Regex::ART($loc) && !Regex::PERM($loc) && !$this->isLibUseOnly()) ||
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

    public function showRemRequest($item_id=null) {
        $item_id = $this->getItemId($item_id);
        $stat = $this->getStatus($item_id);
        $loc = $this->getLocation($item_id);
        $desc = $this->getDescription();

        # Never show on Remote SPC items (PC-439)
        if (Regex::SPEC_COLL_REMOTE($loc)) {
            return false;
        }

        if ( (Regex::REMOTE($loc)) && !Regex::VINYL($desc) ||
             (Regex::THESES_REMOTE($loc))
           ) {
            return true;
        }
        return false;
    }

    public function showRemForm($item_id=null) {
        //XXX Not implementing for now
        return false;
    }

    public function showFacDel($item_id=null) {
        $item_id = $this->getItemId($item_id);
        $stat = $this->getStatus($item_id);
        $loc = $this->getLocation($item_id);
        $desc = $this->getDescription();

        # Never show if the item is out
        if ($this->isOut($item_id)) {
            return false;
        }

        # If all the items are on reserve, return false
        if (!$this->isAllReserve()) {
            return false;
        }

        # Never show on Remote SPC items (PC-439)
        if (Regex::SPEC_COLL_REMOTE($loc)) {
            return false;
        }

        if ( (Regex::ART($loc) && !Regex::PERM($loc) && !$this->isLibUseOnly()) ||
             (Regex::BUSINESS($loc) && !Regex::RESERV($loc)) ||
             (Regex::MAP($loc) && Regex::CIRCULATING($loc) && Regex::AVAILABLE($stat)) ||
             (Regex::MUSIC($loc) && !(Regex::REF($loc) || Regex::RESERV($loc))) ||
             (Regex::REMOTE($loc) && !Regex::VINYL($desc) && !Regex::SPEC_COLL_REMOTE($loc) && !Regex::MICROFORMS($loc)) ||
             (Regex::ROVI($loc)) ||
             (Regex::THESES_REMOTE_MICRO($loc)) ||
             (Regex::MAIN($loc) && Regex::AVAILABLE($stat)) ||
             (Regex::AVAILABLE($stat))
           ) {
            return true;
        }
        return false;
    }

    public function showRemotePat($item_id=null) {
        $item_id = $this->getItemId($item_id);
        $stat = $this->getStatus($item_id);
        $loc = $this->getLocation($item_id);
        $desc = $this->getDescription();

        # Never show if the item is out
        if ($this->isOut($item_id)) {
            return false;
        }

        # If all the items are on reserve, return false
        if (!$this->isAllReserve()) {
            return false;
        }

        # Never show on Remote SPC items (PC-439)
        if (Regex::SPEC_COLL_REMOTE($loc)) {
            return false;
        }

        if ( (Regex::ART($loc) && !Regex::PERM($loc) && !$this->isLibUseOnly()) ||
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

    public function showSpcAeon($item_id=null) {
        $item_id = $this->getItemId($item_id);
        $stat = $this->getStatus($item_id);
        $loc = $this->getLocation($item_id);

        if ((Regex::SPEC_COLL_REMOTE($loc) && (Regex::LIB_USE_ONLY($stat) || Regex::ON_DISPLAY($stat))) ||
             Regex::SPEC_COLL($loc)) {
            return true;
        }
        return false;
    }

    public function showOtherLib($item_id=null) {
        $item_id = $this->getItemId($item_id);
        $loc = $this->getLocation($item_id);

        # Never show on Remote SPC items (PC-439)
        if (Regex::SPEC_COLL_REMOTE($loc)) {
            return false;
        }

        # only show if all items are on reserve, non-circulating (lib use only), or checked out
        if ($this->isAllReserveNonCircOut()) {
            return true;
        }
        return false;

    }

    public function showUahc($item_id=null) {
        $item_id = $this->getItemId($item_id);
        # only show if any of the items in the instance are held by UAHC
        if ($item_id === null) {
            $loc = $this->getLocation($item_id);
            if (Regex::UNIV_ARCH($loc)) {
                return true;
            }
        }
        else {
            foreach ($this->items as $item) {
                $loc = $this->getLocation($item['item_id']);
                if (Regex::UNIV_ARCH($loc)) {
                    return true;
                }
            }
        }
        return false;
    }

    public function showMicrofiche($item_id=null) {
        $item_id = $this->getItemId($item_id);
        if ($item_id === null) {
            $loc = $this->getLocation($item_id);
            if (Regex::MICROFORMS($loc)) {
                return true;
            }
        }
        else {
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
