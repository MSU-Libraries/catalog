<?php

/**
 * Prepares data for the Get This button
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

/**
 * Class to hold data for the Get This button
 *
 * @category VuFind
 * @package  Holdings
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/vufind/ Main page
 */
class GetThisLoader extends AbstractItemLoader
{
    public $msgTemplate; // template to use for servMsg

    /**
     * Initializes the loader with the given record and item data
     *
     * @param object        $record       Record driver object
     * @param object|array  $items        array of holding items
     * @param string        $item_id      holding record data for the current holding item
     * @param PluginManager $configReader Config reader object
     */
    public function __construct($record, $items, $item_id = null, $configReader = null)
    {
        $this->msgTemplate = null;
        parent::__construct($record, $items, $item_id, $configReader);
    }

    /**
     * Check if item is Makerspace equipment location code
     *
     * @param string $item_id Item ID to filter for
     *
     * @return bool
     */
    public function isMakerspace($item_id = null)
    {
        $item_id = $this->getItemId($item_id);
        $loc_code = $this->getLocationCode($item_id);
        return $loc_code == 'mnmst';
    }

    /**
     * Check if item is non-Makerspace equipment
     *
     * @param string $item_id Item ID to filter for
     *
     * @return bool
     */
    public function isEquipment($item_id = null)
    {
        $loc_code = $this->getLocationCode($item_id);
        $item = $this->getItem($item_id);
        // If the location is (DMCTL or DXRES) and material type is '2D/3D/Kit/Equipment'
        // OR
        // If  the call number starts with 'Equipment' or material type is '2D/3D/Kit/Equipment'
        $startsWithEquipment = (stripos($item['callnumber'], 'equipment') === 0);
        $isEquipmentLoc = ($loc_code == 'dmctl' || $loc_code == 'dxres');
        $isEquipmentType = ($item['material_type'] == '2D/3D/Kit/Equipment');

        if (($isEquipmentType || $startsWithEquipment) || ($isEquipmentLoc && $isEquipmentType)) {
            return true;
        }
        return false;
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
        foreach ($this->record->getFormats() as $format) {
            if (Regex::SERIAL($format)) {
                return true;
            }
        }
        return false;
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
            Regex::CHECKED($status) ||
            Regex::BILLED($status) ||
            Regex::ON_SEARCH($status) ||
            Regex::LOST($status) ||
            Regex::HOLD($status)
        ;
    }

    /**
     * Determine if the given item is media of audio/video form
     *
     * @param string $item_id Item ID to filter for
     *
     * @return bool  Whether the item is audio or video media item or not
     */
    public function isAudioVideoMedia($item_id = null)
    {
        $item_id = $this->getItemId($item_id);
        $callNum = $this->getItem($item_id)['callnumber'] ?? '';
        return Regex::AV_MEDIA($callNum);
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
        $callNum = $this->getItem($item_id)['callnumber'] ?? '';
        return Regex::MICROPRINT($callNum) || Regex::AV_MEDIA($callNum);
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
        $item = $this->getItem($item_id);
        [$stat, $_] = $this->getStatusParts($item);
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
            if ($this->isEquipment($item_id)) {
                $this->msgTemplate = 'equipment.phtml';
            } elseif (
                Regex::ART($loc) && Regex::PERM($loc)
                || (!Regex::RESERVE_DIGITAL($loc) && Regex::RESERV($loc))
            ) {
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
                $this->msgTemplate = 'vvlaccess.phtml';
            } elseif (Regex::MUSIC($loc) && Regex::MUSIC_SERVICE_DESK($loc)) {
                $this->msgTemplate = 'ask.phtml';
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
     * Determine if the request scanning from MSU's collection template should display
     *
     * @param string $item_id Item ID to filter for
     *
     * @return bool  If the template should display
     */
    public function showReqScanMSU($item_id = null)
    {
        $item_id = $this->getItemId($item_id);
        $stat = $this->getStatus($item_id);
        $loc = $this->getLocation($item_id);
        $callNum = $this->getItem($item_id)['callnumber'] ?? '';
        if (
            (
                Regex::AVAILABLE($stat)
                || Regex::SPEC_COLL($loc)
                || Regex::SPEC_COLL_REMOTE($loc)
                || Regex::MICROPRINT($callNum)
                || Regex::MICROFORMS($loc)
                || Regex::GOV($loc)
                || Regex::TURFGRASS($loc)
            )
            && !$this->isMakerspace($item_id)
            && !$this->isEquipment($item_id)
            && !$this->isAudioVideoMedia($item_id)
            && !Regex::VINCENT_VOICE($loc)
            && !Regex::MSU_SCAN_EXCLUDE_LOCATION($loc)
        ) {
            return true;
        }
        return false;
    }

    /**
     * Determine if the request scanning from other libraries template should display
     *
     * @param string $item_id Item ID to filter for
     *
     * @return bool  If the template should display
     */
    public function showReqScanOther($item_id = null)
    {
        $item_id = $this->getItemId($item_id);
        $stat = $this->getStatus($item_id);
        $loc = $this->getLocation($item_id);
        $callNum = $this->getItem($item_id)['callnumber'] ?? '';
        if (
            !Regex::AVAILABLE($stat)
            && !$this->isMakerspace($item_id)
            && !$this->isEquipment($item_id)
            && !$this->isAudioVideoMedia($item_id)
            && !Regex::SPEC_COLL($loc)
            && !Regex::SPEC_COLL_REMOTE($loc)
            && !Regex::MICROPRINT($callNum)
            && !Regex::MICROFORMS($loc)
            && !Regex::GOV($loc)
            && !Regex::VINCENT_VOICE($loc)
            && !Regex::TURFGRASS($loc)
        ) {
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
        $callNum = $this->getItem($item_id)['callnumber'] ?? '';

        if (
            $this->isOut($item_id) ||
            $this->isUnavailable($item_id) ||
            Regex::RESERV($loc) ||
            Regex::SPEC_COLL_REMOTE($loc) ||
            Regex::FICHE($loc) ||
            Regex::MICROFORMS($loc) ||
            Regex::MICROPRINT($callNum) ||
            $this->isMakerspace($item_id) ||
            $this->isEquipment($item_id) ||
            (Regex::MUSIC($loc) && Regex::MUSIC_EXCLUDE_REQUEST($loc))
        ) {
            return false;
        }

        if (
            (Regex::ART($loc) && !Regex::PERM($loc) && !$this->isLibUseOnly()) ||
            (Regex::BUSINESS($loc) && !Regex::RESERV($loc)) ||
            (Regex::MAP($loc) && Regex::CIRCULATING($loc) && Regex::AVAILABLE($stat)) ||
            (Regex::MUSIC($loc) && !(Regex::REF($loc) || Regex::RESERV($loc))) ||
            (
                Regex::REMOTE($loc) &&
                !Regex::VINYL($desc) &&
                !Regex::SPEC_COLL_REMOTE($loc) &&
                !Regex::MICROFORMS($loc)
            ) ||
            (Regex::ROVI($loc)) ||
            (Regex::THESES_REMOTE_MICRO($loc)) ||
            (Regex::MAIN($loc) && Regex::AVAILABLE($stat)) ||
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
        $callNum = $this->getItem($item_id)['callnumber'] ?? '';

        if (
            $this->isOut($item_id) ||
            $this->isUnavailable($item_id) ||
            Regex::RESERV($loc) ||
            Regex::SPEC_COLL_REMOTE($loc) ||
            Regex::FICHE($loc) ||
            Regex::MICROFORMS($loc) ||
            Regex::MICROPRINT($callNum) ||
            $this->isMakerspace($item_id) ||
            $this->isEquipment($item_id) ||
            (Regex::MUSIC($loc) && Regex::MUSIC_EXCLUDE_REQUEST($loc))
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
            (Regex::REMOTE($loc) && !Regex::VINYL($desc)) ||
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

        if (
            $this->isMakerspace($item_id)
            || Regex::VINCENT_VOICE($loc)
            || $this->isEquipment($item_id)
            || (Regex::MUSIC($loc) && Regex::MUSIC_EXCLUDE_ILL($loc) && !$this->isUnavailable($item_id))
        ) {
            return false;
        }

        // only if the item is on reserve, non-circulating (lib use only), checked out or unavailable
        if (
            Regex::RESERV($loc) ||
            $this->isOut($item_id) ||
            $this->isLibUseOnly($item_id) ||
            $this->isUnavailable($item_id)
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
        $items = $item_id ? [['item_id' => $item_id]] : $this->items;
        foreach ($items as $item) {
            $loc = $this->getLocation($item['item_id']);
            if (Regex::UNIV_ARCH($loc)) {
                return true;
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
    public function showMicroforms($item_id = null)
    {
        $item_id = $this->getItemId($item_id);
        $items = $item_id ? [['item_id' => $item_id]] : $this->items;
        foreach ($items as $item) {
            $loc = $this->getLocation($item['item_id']);
            $callNum = $this->getItem($item['item_id'])['callnumber'] ?? '';
            if (
                Regex::MICROFORMS($loc) ||
                Regex::FICHE($loc) ||
                Regex::MICROPRINT($callNum)
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Determine if the item is part of the music ROVI Vinyl collection
     *
     * @param string $item_id Item ID to filter for
     *
     * @return bool  If the template should display
     */
    public function isROVIVinyl($item_id = null)
    {
        return Regex::MUSIC_ROVI_VINYL($this->getLocation($item_id));
    }
}
