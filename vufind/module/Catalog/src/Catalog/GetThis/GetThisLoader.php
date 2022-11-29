<?php
namespace Catalog\GetThis;
use Catalog\GetThis\RegexLookup as Regex;

class GetThisLoader {
    public $record;  // record driver
    public $items;   // holding items
    public $msgTemplate; // template to use for servMsg

    function __construct($record, $items) {
        $this->record = $record;
        $this->items = $items;
        $this->msgTemplate = null;
    }

    //TODO when item_id == null, get first available item, if exists; otherwise first item ??
    public function getItem($item_id=null) {
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
        return $this->getItem($item_id)['status'] ?? "Unknown";
    }

    public function getLocation($item_id=null) {
        return $this->getItem($item_id)['location'] ?? "";
    }

    public function isOut($item_id=null) {
        $status = $this->getStatus($item_id);
        return (
            preg_match('/CHECKED/i',$status) ||
            preg_match('/BILLED/i',$status) ||
            preg_match('/ON SEARCH/i', $status) ||
            preg_match('/LOST/i',$status) ||
            preg_match('/HOLD/i', $status)
        );
    }

    public function isMedia($item_id=null) {
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
        $stat = $this->getStatus($item_id);
        return Regex::LIB_USE_ONLY($stat);
    }

    public function showInProcess($item_id=null) {
        $stat = $this->getStatus($item_id);
        return Regex::IN_PROCESS($stat);
    }

    public function showServMsg($item_id=null) {
        $stat = $this->getStatus($item_id);
        $loc = $this->getLocation($item_id);
        if ($this->isOut($item_id)) {
            if (Regex::MAKERSPACE($loc)) {
                $this->msgTemplate = 'makercheckedout.phtml';
            }
        }
        else {
            if (Regex::UNIV_ARCH($loc)) {
                $this->msgTemplate = 'univarch.phtml';
            }
            elseif (Regex::ART($loc) && Regex::PERM($loc)) {
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
                $this->msgTemplate = 'gamae.phtml';
            }
            elseif (Regex::LAW_RESERVE($loc)) {
                $this->msgTemplate = 'lawreserve.phtml';
            }
            elseif (Regex::LAW_RARE_BOOK($loc)) {
                $this->msgTemplate = 'lawrare.phtml';
            }
            //TODO SCHAEFER && status not AVAILABLE : servMsg("Requet this Item", $lawmsg)
            //TODO MICROFORMS
            //      && REMOTE : servMsg("Request this item",$mfremote.$libonly)
            //      && not REMOTE : ask.phtml
            //TODO MAKERSPACE : servMsg($SELF_SERV, $MAKERAVAIL)
            //TODO MAP
            //      && CIRCULATING && isLibUseOnly() : ask.phtml
            //      && not CIRCULATING : servMsg("Request this item", $MAP_PICKUP)
            // MUSIC && RESERV : reserve3day.phtml      -- unneeded, let RESERV catch this
            // BUSINESS && RESERV : reserve3day.phtml   -- unneeded, let RESERV catch this
            // KLINE_DMC && RESERV : reserve3day.phtml  -- unneeded, let RESERV catch this
            //TODO RESERV : reserve3day.phtml
            //TODO TURFGRASS : servMsg('Turfgrass Information Center', $TURFMSG)
            //TODO VINCENT_VOICE : pickup.phtml
        }
        return $this->msgTemplate !== null;
    }

    public function showReqItem($item_id=null) {
        //TODO
        return true;
    }

    public function showGetRovi($item_id=null) {
        //TODO
        return true;
    }

    public function showLockerPick($item_id=null) {
        //TODO
        return true;
    }

    public function showRemRequest($item_id=null) {
        //TODO
        return true;
    }

    public function showScanCopy($item_id=null) {
        //TODO
        return true;
    }

    public function showRemForm($item_id=null) {
        //TODO
        return true;
    }

    public function showFacDel($item_id=null) {
        //TODO
        return true;
    }

    public function showRemotePat($item_id=null) {
        //TODO
        return true;
    }

    public function showGulForm($item_id=null) {
        //TODO
        return true;
    }

    public function showSpcAeon($item_id=null) {
        //TODO
        return true;
    }

    public function showOtherLib($item_id=null) {
        $stat = $this->getStatus($item_id);
        $loc = $this->getLocation($item_id);
        $desc = $this->record->getSummary(); // TODO how to get actual description?

        if ($this->showInProcess($item_id)) {
            return false;
        }
        if ($this->isOut($item_id) && !Regex::MAKERSPACE($loc)) {
            return true;
        }
        if (!$this->isOut($item_id) && (
                (Regex::ART($loc) && Regex::PERM($loc)) ||
                Regex::LIB_OF_MICH($loc) ||
                Regex::REFERENCE($loc) ||
                Regex::DIGITAL_MEDIA($loc) ||
                Regex::SCHAEFER($loc) ||
                Regex::MICROFORMS($loc) ||
                Regex::GOV($loc) ||
                (Regex::MAP($loc) && Regex::CIRCULATION($loc) && Regex::LIB_USE_ONLY($stat)) ||
                (Regex::REMOTE($loc) && Regex::VINYL($desc)) ||
                Regex::RESERV($loc) ||
                (Regex::SPEC_COLL_REMOTE($loc) && (Regex::LIB_USE_ONLY($stat) || Regex::ON_DISPLAY($stat))) ||
                Regex::SPEC_COLL($loc) ||
                (Regex::TURFGRASS($loc) && !$this->isMedia($item_id)) ||
                Regex::VINCENT_VOICE($loc) ||
                !Regex::AVAILABLE($stat)
            )) {
            return true;
        }
        return false;
    }
}
