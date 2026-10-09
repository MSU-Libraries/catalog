<?php

/**
 * Prepares data for the Get This button.
 *
 * PHP version 8
 *
 * Copyright (C) Michigan State University Board of Trustees 2025.
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
 * along with this program; if not, see <https://www.gnu.org/licenses/>
 *
 * @category VuFind
 * @package  GetThis
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Catalog\GetThis;

use Catalog\View\Helper\Root\Record;
use Exception;
use Throwable;

use function count;

/**
 * Class to hold data for the Get This button.
 *
 * @category VuFind
 * @package  GetThis
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/vufind/ Main page
 */
class GetThisLoader extends \VuFind\GetThis\GetThisLoader
{
    /**
     * Get the templates to display according to the config file.
     * MSU TODO remove when upgrading core VF, error more verbose
     *
     * @return array
     * @throws Exception
     */
    public function getSubTemplates(): array
    {
        if (isset($this->subTemplates)) {
            return $this->subTemplates;
        }
        try {
            foreach ($this->config['templates'] ?? [] as $templateName => $template) {
                // If the enabled attribute is not present, we display the template
                if ($template['enabled'] ?? true) {
                    // If condition_function is not present we display the templates
                    // If it's present we display the template only if the function exists and return true
                    if (
                        !isset($template['condition_function']) && !isset($template['condition_group'])
                        || $this->areConditionsFilled($template)
                    ) {
                        $this->addSubTemplates($templateName, $template);
                    }
                }
            }
        } catch (Throwable $t) {
            throw new Exception(
                'Error with the get this configuration : "'
                . $t->getMessage() . '" in ' . $t->getFile() . ' line ' . $t->getLine(),
                previous: $t
            );
        }
        $this->sortSubTemplateParams();
        return $this->subTemplates ?? [];
    }

    /**
     * Determine if the University Archives template should display
     *
     * @param ?string $itemId Item ID to filter for
     *
     * @return bool  If the template should display
     */
    public function showUArchive(?string $itemId = null): bool
    {
        foreach ($this->getItems() as $item) {
            $location = $this->getLocation($item['item_id']);
            if ($this->matches('LOCATION_UNIV_ARCH', $location)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Determine if the record can be delivered.
     *
     * @param ?string $itemId Item ID to filter for
     *
     * @return bool  If the template should display
     */
    protected function showDelivery(?string $itemId = null): bool
    {
        $item = $this->getItem($itemId);
        $location = $this->getLocation($itemId);
        $callNum = $item['callnumber'] ?? '';
        if (
            $this->matches('LOCATION_RESERV', $location)
            || $this->matches('LOCATION_SPEC_COLL_REMOTE', $location)
            || $this->matches('LOCATION_FICHE', $location)
            || $this->matches('LOCATION_MAKERSPACE', $location)
            || $this->matches('LOCATION_MICROFORMS', $location)
            || $this->matches('CALLNUMBER_MICROPRINT', $callNum)
            || (
                $this->matches('LOCATION_MUSIC', $location)
                && $this->matches('LOCATION_MUSIC_EXCLUDE_REQUEST', $location)
            )
        ) {
            return false;
        }
        return true;
    }

    /**
     * Determine if the faculty delivery template should display.
     *
     * @param ?string $itemId Item ID to filter for
     *
     * @return bool  If the template should display
     */
    public function showStaffDelivery(?string $itemId = null): bool
    {
        if (parent::showStaffDelivery() === false || $this->showDelivery($itemId) === false) {
            return false;
        }

        $item = $this->getItem($itemId);
        $location = $this->getLocation($itemId);

        if (
            (
                $this->matches('LOCATION_ART', $location)
                && !$this->matches('LOCATION_PERM', $location)
                && !$this->isLibUseOnly()
            )
            || $this->matches('LOCATION_BUSINESS', $location)
            || (
                $this->matches('LOCATION_MAP', $location)
                && $this->matches('LOCATION_CIRCULATING', $location)
            )
            || (
                $this->matches('LOCATION_MUSIC', $location)
                && !$this->matches('LOCATION_REF', $location)
            )
            || (
                $this->matches('LOCATION_REMOTE', $location)
                && !$this->isVinyl()
                && !$this->matches('LOCATION_SPEC_COLL_REMOTE', $location)
                && !$this->matches('LOCATION_MICROFORMS', $location)
            )
            || $this->matches('LOCATION_ROVI', $location)
            || $this->matches('LOCATION_THESES_REMOTE_MICRO', $location)
            || $this->matches('LOCATION_MAIN', $location)
        ) {
            $availability = $item['availability']->getStatusDescription();
            return !empty($availability) && $this->matches('STATUS_AVAILABLE', $availability);
        }

        return false;
    }

    /**
     * Determine if the remote parton template should display.
     *
     * @param ?string $itemId Item ID to filter for
     *
     * @return bool  If the template should display
     */
    public function showRemoteDelivery(?string $itemId = null): bool
    {
        if (parent::showRemoteDelivery() === false || $this->showDelivery($itemId) === false) {
            return false;
        }

        $item = $this->getItem($itemId);
        $location = $this->getLocation($itemId);

        if (
            (
                $this->matches('LOCATION_ART', $location)
                && !$this->matches('LOCATION_PERM', $location)
                && !$this->isLibUseOnly()
            )
            || $this->matches('LOCATION_BROWSING', $location)
            || $this->matches('LOCATION_BUSINESS', $location)
            || $this->matches('LOCATION_CAREER', $location)
            || $this->matches('LOCATION_CESAR_CHAVEZ', $location)
            || $this->matches('LOCATION_VIDEO_GAME', $location)
            || $this->matches('LOCATION_KLINE_DMC', $location)
            || $this->matches('LOCATION_FACULTY_BOOK', $location)
            || $this->matches('LOCATION_SCHAEFER', $location)
            || $this->matches('LOCATION_GOV', $location)
            || (
                $this->matches('LOCATION_MAP', $location)
                && $this->matches('LOCATION_CIRCULATING', $location)
            )
            || (
                $this->matches('LOCATION_MUSIC', $location)
                && !$this->matches('LOCATION_REF', $location)
            )
            || ($this->matches('LOCATION_REMOTE', $location) && !$this->isVinyl())
            || $this->matches('LOCATION_ROVI', $location)
            || $this->matches('LOCATION_TRAVEL', $location)
            || $this->matches('LOCATION_MAIN', $location)
        ) {
            $availability = $item['availability']->getStatusDescription();
            return !empty($availability)
                && $this->matches('STATUS_AVAILABLE', $availability);
        }
        return false;
    }

    /**
     * Determine if the other library links template should display
     *
     * @param ?string $itemId Item ID to filter for
     *
     * @return bool  If the template should display
     */
    public function showInterLibrary(?string $itemId = null): bool
    {
        $location = $this->getLocation($itemId);
        return parent::showInterLibrary($itemId) || $this->matches('LOCATION_RESERV', $location);
    }

    /**
     * Determine if the SPC/Aeon request template should display
     *
     * @param ?string $itemId Item ID to filter for
     *
     * @return bool  If the template should display
     */
    public function showSpecialCollection(?string $itemId = null): bool
    {
        $status = $this->getStatus($itemId);
        $location = $this->getLocation($itemId);

        if (
            (
                $this->matches('LOCATION_SPEC_COLL_REMOTE', $location)
                && (
                    $this->matches('STATUS_LIB_USE_ONLY', $status)
                    || $this->matches('STATUS_STATUS_ON_DISPLAY', $status)
                )
            )
            || $this->matches('LOCATION_SPEC_COLL', $location)
        ) {
            return true;
        }
        return false;
    }

    /**
     * Return request scan parameter.
     *
     * @return string
     */
    public function getRequestScanParam(): string
    {
        $params = [];

        $authors = $this->recordDriver->getDeduplicatedAuthors();
        $authors = array_keys(
            array_merge(
                $authors['primary'],
                $authors['secondary'],
                $authors['corporate'],
            )
        );
        $params['PhotoArticleAuthor'] = count($authors) == 1 ? $authors[0] : '';

        $isxns = array_merge($this->recordDriver->getISSNs(), $this->recordDriver->getISBNs());
        $params['ISSN'] = count($isxns) == 1 ? $isxns[0] : '';
        // Clean the ISxN
        if ($pos = strpos($params['ISSN'], ' ')) {
            $params['ISSN'] = substr($params['ISSN'], 0, $pos);
        }

        $params['PhotoJournalTitle'] = $this->recordDriver->getTitle();
        $params['CallNumber'] = $this->getCallNumber()['text'];
        $params['PhotoJournalVolume'] = $this->getItem()['enumchron'] ?? '';

        $paramStr = '';
        foreach ($params as $pkey => $pval) {
            if (!empty($pval)) {
                $paramStr .= "&amp;{$pkey}=" . urlencode($pval);
            }
        }
        return $paramStr;
    }

    /**
     * Determine if the request scanning from MSU's collection template should display
     *
     * @param ?string $itemId Item ID to filter for
     *
     * @return bool  If the template should display
     */
    public function showRequestScanMSU(?string $itemId = null)
    {
        $status = $this->getStatus($itemId);
        $location = $this->getLocation($itemId);
        $callNum = $this->getItem($itemId)['callnumber'] ?? '';
        if (
            (
                $this->matches('STATUS_AVAILABLE', $status)
                || $this->matches('LOCATION_SPEC_COLL', $location)
                || $this->matches('LOCATION_SPEC_COLL_REMOTE', $location)
                || $this->matches('CALLNUMBER_MICROPRINT', $callNum)
                || $this->matches('LOCATION_MICROFORMS', $location)
                || $this->matches('LOCATION_GOV', $location)
                || $this->matches('LOCATION_TURFGRASS', $location)
            )
            && !$this->matches('LOCATION_MSU_SCAN_EXCLUDE_LOCATION', $location)
        ) {
            $this->setSubTemplateParam(
                'request-scan-msu',
                'param',
                $this->getRequestScanParam()
            );
            return true;
        }
        return false;
    }

    /**
     * Determine if the request scanning from other libraries template should display
     *
     * @param ?string $itemId Item ID to filter for
     *
     * @return bool  If the template should display
     */
    public function showRequestScanOther(?string $itemId = null): bool
    {
        $status = $this->getStatus($itemId);
        $location = $this->getLocation($itemId);
        $callNum = $this->getItem($itemId)['callnumber'] ?? '';
        if (
            !$this->matches('STATUS_AVAILABLE', $status)
            && !$this->matches('LOCATION_SPEC_COLL', $location)
            && !$this->matches('LOCATION_SPEC_COLL_REMOTE', $location)
            && !$this->matches('CALLNUMBER_MICROPRINT', $callNum)
            && !$this->matches('LOCATION_MICROFORMS', $location)
            && !$this->matches('LOCATION_GOV', $location)
            && !$this->matches('LOCATION_VINCENT_VOICE', $location)
            && !$this->matches('LOCATION_TURFGRASS', $location)
            && !$this->matches('LOCATION_MSU_SCAN_EXCLUDE_LOCATION', $location)
        ) {
            $this->setSubTemplateParam(
                'request-scan-other',
                'param',
                $this->getRequestScanParam()
            );
            return true;
        }
        return false;
    }

    /**
     * Determine if the message forms should display
     * TODO replace by configs in getThis.yaml when regex is implemented
     *
     * @return bool
     */
    public function showMessage(): bool
    {
        $status = $this->getStatus();
        $location = $this->getLocation();

        if ($this->isOut()) {
            if ($this->matches('LOCATION_MAKERSPACE', $location)) {
                $template = 'makerspace-checked-out';
            }
        } else {
            if ($this->matches('STATUS_IN_PROCESS', $status)) {
                $template = 'in-process';
            } elseif ($this->isEquipment()) {
                $template = 'equipment';
            } elseif (
                $this->matches('LOCATION_ART', $location)
                && $this->matches('LOCATION_PERM', $location)
                || (
                    !$this->matches('LOCATION_RESERVE_DIGITAL', $location)
                    && $this->matches('LOCATION_RESERV', $location)
                )
            ) {
                $template = 'reserve';
            } elseif (
                $this->matches('LOCATION_ART', $location)
                || $this->matches('LOCATION_REFERENCE', $location)
            ) {
                $template = 'ask';
            } elseif ($this->matches('LOCATION_RESERVE_DIGITAL', $location)) {
                if ($this->matches('STATUS_AVAILABLE', $status)) {
                    $template = 'digital-lending-available';
                } else {
                    $template = 'digital-lending-checked-out';
                }
            } elseif (
                $this->matches('LOCATION_DIGITAL_MEDIA', $location)
                || (
                    $this->matches('LOCATION_MUSIC', $location)
                    && $this->matches('LOCATION_REF', $location)
                )
            ) {
                $template = 'pickup';
            } elseif ($this->matches('LOCATION_VIDEO_GAME', $location)) {
                $template = 'game';
            } elseif ($this->matches('LOCATION_LAW_RESERVE', $location)) {
                $template = 'law-reserve';
            } elseif ($this->matches('LOCATION_LAW_RARE_BOOK', $location)) {
                $template = 'law-rare';
            } elseif (
                $this->matches('LOCATION_SCHAEFER', $location)
                && !$this->matches('STATUS_AVAILABLE', $status)
            ) {
                $template = 'law';
            } elseif ($this->matches('LOCATION_MAKERSPACE', $location)) {
                $template = 'makerspace';
            } elseif ($this->matches('LOCATION_MAP', $location)) {
                if ($this->matches('LOCATION_CIRCULATING', $location) && $this->isLibUseOnly()) {
                    $template = 'ask';
                } elseif (!$this->matches('LOCATION_CIRCULATING', $location)) {
                    $template = 'map-pickup';
                }
            } elseif ($this->matches('LOCATION_TURFGRASS', $location)) {
                $template = 'turfgrass';
            } elseif ($this->matches('LOCATION_VINCENT_VOICE', $location)) {
                $template = 'vvl-access';
            } elseif (
                $this->matches('LOCATION_MUSIC', $location)
                && $this->matches('LOCATION_MUSIC_SERVICE_DESK', $location)
            ) {
                $template = 'ask';
            } elseif ($this->matches('LOCATION_ALUMNI_CHAPEL', $location)) {
                $template = 'alumni-chapel';
            }
        }
        if (isset($template)) {
            $this->setSubTemplateParam('message', 'template', $template);
        }
        return isset($template);
    }

    /**
     * Returns if the current record is an HLM record or not
     *
     * @return bool Depending on if the current record has the hlm prefix or not
     */
    public function isHLM(): bool
    {
        try {
            $uniqueID = $this->recordDriver->getUniqueID();
        } catch (Exception) {
            return false;
        }
        return str_starts_with($uniqueID, 'hlm.');
    }

    /**
     * Whether the location matches the Shaefer library
     *
     * @return bool
     */
    public function isLawLibrary(): bool
    {
        return $this->matches('LOCATION_SCHAEFER', $this->getLocation());
    }

    /**
     * Check if item is non-Makerspace equipment
     *
     * @return bool
     */
    public function isEquipment(): bool
    {
        $locationCode = $this->getLocationCode(); // Also checked in LOCATION_EXCLUSIVE regex
        $item = $this->getItem();
        // If any of :
        // the location is (DMCTL or DXRES)
        // the material type is '2D/3D/Kit/Equipment'
        // the call number starts with 'Equipment'
        $startsWithEquipment = (stripos($item['callnumber'], 'equipment') === 0);
        $isEquipmentLocation = ($locationCode == 'dmctl' || $locationCode == 'dxres');
        $isEquipmentType = ($item['material_type'] == '2D/3D/Kit/Equipment');

        return $isEquipmentType || $startsWithEquipment || $isEquipmentLocation;
    }

    /**
     * Whether the description contains vinyl (case-insensitive)
     *
     * @return bool
     */
    public function isVinyl(): bool
    {
        // TODO how to get actual description?
        $data = $this->recordDriver->getSummary();

        foreach ($data as $item) {
            if (isset($item['link']) && stripos($item['link'], 'VINYL') !== false) {
                return true;
            }
            if (isset($item['note']) && stripos($item['note'], 'VINYL') !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Determine if the given item is a media item or not
     *
     * @return bool
     */
    public function isMedia()
    {
        $callNum = $this->getItem()['callnumber'] ?? '';
        return $this->matches('CALLNUMBER_MICROPRINT', $callNum) || $this->isAudioVideoMedia();
    }

    /**
     * Check if item is Makerspace equipment location code
     *
     * @return bool
     */
    public function isMakerspace()
    {
        return $this->matches('LOCATION_MAKERSPACE', $this->getLocation());
    }

    /**
     * Determine if the item is part of the music ROVI Vinyl collection
     *
     * @return bool  If the template should display
     */
    public function isROVIVinyl()
    {
        return $this->matches('LOCATION_MUSIC_ROVI_VINYL', $this->getLocation());
    }

    /**
     * Determine if the item is part of the music ROVI Vinyl collection
     *
     * @return bool  If the template should display
     */
    public function isReadingRoom()
    {
        return $this->matches('LOCATION_READING_ROOM', $this->getLocation());
    }

    /**
     * Match a given value against a given regex (from Regex.yaml)
     *
     * @param string $value The value (ie: location, callnumber...)
     * @param string $regex The name of the regex in Regex.yaml
     *
     * @return bool
     */
    public function is(string $value, string $regex): bool
    {
        return $this->matches($regex, $value);
    }

    /**
     * Update the items' AvailabilityStatus.
     *
     * @param Record $record the record
     *
     * @return void
     */
    public function updateAvailabilityStatus(Record $record)
    {
        if (!isset($this->items)) {
            return;
        }
        foreach ($this->items as &$item) {
            $record->updateAvailabilityStatus($item);
        }
    }
}
