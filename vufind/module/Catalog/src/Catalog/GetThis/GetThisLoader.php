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
            elseif (Regex::ART($loc)) {
                $this->msgTemplate = 'ask.phtml';
            }
            //TODO BUSINESS && RESERVE : reserve3day.phtml
            //TODO RESERVE_DIGITAL
            //      && status AVAILABLE : servMsg("Request Digital Lending",$CDL_INSTRUCTION)
            //      && status not AVAILABLE : servMsg("Request Digital Lending",$CDL_CHECKEDOUT)
            //TODO REFERENCE : ask.phtml
            //TODO VIDEO_GAME : servMsg("Request this Item", $rvgame.$libonly)
            //TODO DIGITAL_MEDIA : pickup.phtml
            //TODO KLINE_DMC && RESERV : reserve3day.phtml
            //TODO LAW_RESERVE : servMsg($SELF_SERV, $DXRESERVE)
            //TODO LAW_RARE_BOOK : servMsg($SELF_SERV, $dxrbkmsg)
            //TODO SCHAEFER && status not AVAILABLE : servMsg("Requet this Item", $lawmsg)
            //TODO MICROFORMS
            //      && REMOTE : servMsg("Request this item",$mfremote.$libonly)
            //      && not REMOTE : ask.phtml
            //TODO MAKERSPACE : servMsg($SELF_SERV, $MAKERAVAIL)
            //TODO MAP
            //      && CIRCULATING && isLibUseOnly() : ask.phtml
            //      && not CIRCULATING : servMsg("Request this item", $MAP_PICKUP)
            //TODO MUSIC
            //      && REF : pickup.phtml
            //      && RESERV : reserve3day.phtml
            //TODO RESERVE : reserve3day.phtml
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
        $desc = "TODO"; //$desc = $this->record->getDescription();

        // status not "IN PROCESS"
        if ($this->showInProcess($item_id)) {
            return false;
        }
        // isOut() is true and
        //   location is not MAKERSPACE
        if ($this->isOut($item_id) && !Regex::MAKERSPACE($loc)) {
            return true;
        }
        // isOut() is false and one of
        //   location matches ^MSU ART LIBRARY and PERM
        //   location matches MSU BUSINESS and RESERVE
        //   location matches BROWSING and status not AVAILABLE
        //   location matches CAREER and status not AVAILABLE
        //   location matches RESERVE DIGITAL
        //   location matches LIB OF MICH
        //   location matches REFERENCE
        //   location matches CESAR CHAVEZ and status not AVAILABLE
        //   location matches "DIGITAL/MEDIA."
        //   location matches "^MSU G.M.KLINE DIGITAL" and
        //     location matches RESERV or status not AVAILABLE
        //   location matches FACULTY BOOK and status not AVAILABLE
        //   location matches ^MSU SCHAEFER
        //   location matches ^MSU MICROFORMS
        //   location matches ^MSU GOV
        //   location matches ^MSU MAP and location matches either of CIRCULATING or FOLD
        //     and either isOut() or status matches LIB USE ONLY
        //   location matches ^MSU MUSIC LIBRARY and location matches RESERV
        //   location matches ^MSU REMOTE and description matches VINYL
        //   location matches RESERVE
        //   location matches SPEC COLL REMOTE and
        //     status matches either LIB USE ONLY or ON DISPLAY
        //   location matches ^MSU SPEC COLL or SPECIAL COLLECTION
        //   location matches TRAVEL and status not AVAILABLE
        //   location matches MSU BEARD or ^MSU TURFGRASS and isMedia() is false
        //   location matches ^MSU VINCENT VOICE
        //   location matches ^MSU MAIN and status not AVAILABLE
        //   status not AVAILABLE
        if (!$this->isOut($item_id) && (
                (Regex::ART($loc) && Regex::PERM($loc)) ||
                (Regex::BUSINESS($loc) && Regex::RESERVE($loc)) ||
                Regex::RESERVE_DIGITAL($loc) ||
                Regex::LIB_OF_MICH($loc) ||
                Regex::REFERENCE($loc) ||
                Regex::DIGITAL_MEDIA($loc) ||
                (Regex::KLINE_DMC($loc) && Regex::RESERV($loc)) ||
                Regex::SCHAEFER($loc) ||
                Regex::MICROFORMS($loc) ||
                Regex::GOV($loc) ||
                (Regex::MAP($loc) && Regex::CIRCULATION($loc) && Regex::LIB_USE_ONLY($stat)) ||
                (Regex::MUSIC($loc) && Regex::RESERV($loc)) ||
                (Regex::REMOTE($loc) && Regex::VINYL($desc)) ||
                Regex::RESERVE($loc) ||
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
