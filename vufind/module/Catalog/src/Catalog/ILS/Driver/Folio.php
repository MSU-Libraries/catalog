<?php

/**
 * FOLIO REST API driver
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018-2023.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */

namespace Catalog\ILS\Driver;

use ArrayIterator;
use Catalog\Utils\RegexLookup as Regex;
use Laminas\Http\Header\HeaderInterface;
use VuFind\Exception\ILS as ILSException;
use VuFind\ILS\Logic\AvailabilityStatus;

use function count;
use function in_array;
use function is_int;
use function is_object;
use function is_string;

/**
 * FOLIO REST API driver
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class Folio extends \VuFind\ILS\Driver\Folio
{
    /**
     * Configuration file reader object (PluginManager)
     *
     * @var \VuFind\Config\PluginManager
     */
    protected $configReader = null;

    /**
     * Constructor
     * MSUL PC-1416 customized to add configReader param for reading msul.ini
     *
     * @param \VuFind\Date\Converter       $dateConverter  Date converter object
     * @param callable                     $sessionFactory Factory function returning
     * SessionContainer object
     * @param \VuFind\Config\PluginManager $configReader   Config reader object
     */
    public function __construct(
        \VuFind\Date\Converter $dateConverter,
        $sessionFactory,
        $configReader,
    ) {
        $this->dateConverter = $dateConverter;
        $this->sessionFactory = $sessionFactory;
        $this->configReader = $configReader; // MSUL PC-1416 New param to read msul.ini
    }

    /**
     * Given an instance object or identifier, or a holding or item identifier,
     * determine an appropriate value to use as VuFind's bibliographic ID.
     *
     * @param string $instanceOrInstanceId Instance object or ID (will be looked up
     * using holding or item ID if not provided)
     * @param string $holdingId            Holding-level id (optional)
     * @param string $itemId               Item-level id (optional)
     *
     * @return string Appropriate bib id retrieved from FOLIO identifiers
     */
    protected function getBibId(
        $instanceOrInstanceId = null,
        $holdingId = null,
        $itemId = null
    ) {
        $idType = $this->getBibIdType();

        // Special case: if we're using instance IDs and we already have one,
        // short-circuit the lookup process:
        if ($idType === 'instance' && is_string($instanceOrInstanceId)) {
            return $instanceOrInstanceId;
        }

        $instance = is_object($instanceOrInstanceId)
            ? $instanceOrInstanceId
            : $this->getInstanceById($instanceOrInstanceId, $holdingId, $itemId);

        switch ($idType) {
            case 'hrid':
                return 'folio.' . $instance->hrid; // MSUL folio prefix (until multibackend fixed)
            case 'instance':
                return $instance->id;
        }

        throw new \Exception('Unsupported ID type: ' . $idType);
    }

    /**
     * Support method for getHoldings() -- given a few key details, format an item
     * for inclusion in the return value.
     *
     * @param string     $bibId            Current bibliographic ID
     * @param array      $holdingDetails   Holding details produced by
     *                                     getHoldingDetailsForItem()
     * @param object     $item             FOLIO item record (decoded from JSON)
     * @param int        $number           The current item number (position within
     *                                     current holdings record)
     * @param string     $dueDateValue     The due date to display to the user
     * @param array      $boundWithRecords Any bib records this holding is bound with
     * @param ?\stdClass $currentLoan      Any current loan on this item
     *
     * @return array
     */
    protected function formatHoldingItem(
        string $bibId,
        array $holdingDetails,
        $item,
        $number,
        string $dueDateValue,
        $boundWithRecords,
        $currentLoan
    ): array {
        $itemNotes = array_filter(
            array_map([$this, 'formatNote'], $item->notes ?? [])
        );
        $locationId = $item->effectiveLocation->id;

        // concatenate enumeration fields if present
        $enum = implode(
            ' ',
            array_filter(
                [
                    $item->volume ?? null,
                    $item->enumeration ?? null,
                    $item->chronology ?? null,
                ]
            )
        );
        $enum = str_ends_with($holdingDetails['holdingCallNumber'], $enum) ? '' : $enum; // MSU
        $callNumberData = $this->chooseCallNumber(
            $holdingDetails['holdingCallNumberPrefix'],
            $holdingDetails['holdingCallNumber'],
            $item->effectiveCallNumberComponents->prefix
                ?? $item->itemLevelCallNumberPrefix ?? '',
            $item->effectiveCallNumberComponents->callNumber
                ?? $item->itemLevelCallNumber ?? ''
        );
        $locAndHoldings = $this->getItemFieldsFromNonItemData($locationId, $holdingDetails, $currentLoan);

        $loanTypeName = '';
        $tempLoanTypeId = $item->temporaryLoanType->id ?? '';
        $permLoanTypeId = $item->permanentLoanType->id ?? '';
        $loanTypeId = !empty($tempLoanTypeId) ? $tempLoanTypeId : $permLoanTypeId;
        if (!empty($loanTypeId)) {
            $loanData = $this->getLoanTypeData($loanTypeId);
            $loanTypeName = $loanData['name'];
        }
        // MSU START
        // PC-835: Items with loan type "Non Circulating" should show as "Lib Use Only" after they're checked in
        if (
            $permLoanTypeId == 'adac93ac-951f-4f42-ab32-79f4faeabb50' &&
            $item->status->name == 'Available' &&
            !Regex::ONLINE($this->getLocationData($locationId)['name'])
        ) {
            $item->status->name = 'Restricted';
        }
        // PC-1416: If the location code is mnmn (the generic "Main library" code), then
        // attempt to get the location data from helm if we have a callnumber
        if ($locAndHoldings['location_code'] == 'mnmn' && !empty($callNumberData['callnumber'])) {
            // Prase the config and get the required data
            $msulConfig = $this->configReader->get('msul');
            if (isset($msulConfig)) {
                $apiUrl = $msulConfig['Locations']['api_url'] ?? '';
                $topKey = $msulConfig['Locations']['response_top_key'] ?? 'callnumbers';
                $floorKey = $msulConfig['Locations']['response_floor_key'] ?? '';
                $locationKey = $msulConfig['Locations']['response_location_key'] ?? '';

                if (!empty($apiUrl)) {
                    // Replace %%callnumber%% with the real callnumber
                    $apiUrl = str_replace('%%callnumber%%', urlencode($callNumberData['callnumber']), $apiUrl);

                    // Get the API data
                    $data = $this->getCachedData($apiUrl);
                    if ($data == null) {
                        try {
                            $response = $this->makeExternalRequest('GET', $apiUrl);
                            $data = json_decode($response->getBody());
                            $this->putCachedData($apiUrl, $data);
                        } catch (ILSException $e) {
                            // We don't care if there are issues with the API, just log it and ignore
                            $this->logWarning(
                                'Could not get location data for callnumber '
                                . $callNumberData['callnumber'] . ' (' . $bibId . ')'
                            );
                        }
                    }

                    // Parse the response and add to our location results
                    if (isset($data->$topKey) && count($data->$topKey) >= 1) {
                        $floor = $floorKey ? ($data->$topKey[0]->$floorKey ?? '') : '';
                        $location = $locationKey ? ($data->$topKey[0]->$locationKey ?? '') : '';

                        $floorPart = !empty($floor) ? ' - ' . $floor : '';
                        $locationPart = !empty($location) ? '(' . $location . ')' : '';
                        $combinedPart = $floorPart . ' ' . $locationPart;

                        if (!empty(trim($combinedPart))) {
                            $locAndHoldings['location'] = trim($locAndHoldings['location'] . $combinedPart);
                            $this->debug(
                                'Found additional location data for callnumber ' . $callNumberData['callnumber'] .
                                ' (' . $bibId . ')' . '. Updating location to: ' . $locAndHoldings['location']
                            );
                        }
                    } else {
                        $this->debug(
                            'No data found for callnumber '
                            . $callNumberData['callnumber'] . ' (' . $bibId . ')'
                        );
                        $this->debug(var_export($data, 1));
                    }
                }
            }
        }
        // MSU END
        return $callNumberData + $locAndHoldings + [
            'id' => $bibId,
            'item_id' => $item->id,
            'holdings_id' => $holdingDetails['id'],
            'number' => $number,
            'enumchron' => $enum,
            'barcode' => $item->barcode ?? '',
            'status' => $item->status->name,
            'duedate' => $dueDateValue,
            'availability' => $item->status->name == 'Available',
            'item_notes' => !empty(implode($itemNotes)) ? $itemNotes : null,
            'reserve' => 'TODO',
            'addLink' => 'check',
            'bound_with_records' => $boundWithRecords,
            'loan_type_id' => $loanTypeId,
            'loan_type_name' => $loanTypeName,
            'issues' => $holdingDetails['holdingsStatements'], // MSU
            'electronic_access' => $item->electronicAccess, // MSU
            'material_type' => $item->materialType->name ?? '', // MSU PC-1426
        ];
    }

    /**
     * Support method for getHoldings() -- processes a FOLIO item
     *
     * @param string $bibId            Bib-level id
     * @param array  $holdingDetails   details for the holding
     * @param object $item             item to process
     * @param int    $dueDateItemCount number of times getCurrentLoan()/getDueDate() were called (passed by reference)
     * @param int    $number           item number
     *
     * @return array An associative array
     */
    protected function processItem($bibId, $holdingDetails, $item, &$dueDateItemCount, $number)
    {
        $copyNumber = $item->copyNumber ?? null; // MSU
        $showDueDate = $this->config['Availability']['showDueDate'] ?? true;
        $showTime = $this->config['Availability']['showTime'] ?? false;
        $maxNumDueDateItems = $this->config['Availability']['maxNumberItems'] ?? 5;
        $currentLoan = null;
        $dueDateValue = '';
        $boundWithRecords = null;
        if (
            $item->status->name == 'Checked out'
            && $showDueDate
            && $dueDateItemCount < $maxNumDueDateItems
        ) {
            $currentLoan = $this->getCurrentLoan($item->id);
            $dueDateValue = $currentLoan ? $this->getDueDate($currentLoan, $showTime) : '';
            $dueDateItemCount++;
        }
        if ($item->isBoundWith ?? false) {
            $boundWithRecords = $this->getBoundWithRecords($item);
        }
        $nextItem = $this->formatHoldingItem(
            $bibId,
            $holdingDetails,
            $item,
            $copyNumber, // MSU, use copyNumber instead of number
            $dueDateValue,
            $boundWithRecords ?? [],
            $currentLoan
        );
        return $nextItem;
    }

    /**
     * Support method for getHoldings() -- processes FOLIO records for a single instance
     *
     * @param string   $bibId      Bib-level id
     * @param object[] $holdings   holdings for the instance
     * @param object[] $folioItems items to look into to find the holdings items
     *
     * @return array An associative array with information about the instance holdings
     */
    protected function processInstanceHoldings($bibId, $holdings, $folioItems)
    {
        $showHoldingsNoItems = $this->config['Holdings']['show_holdings_no_items'] ?? false;
        $dueDateItemCount = 0;
        $items = [];
        $vufindItemSort = $this->config['Holdings']['vufind_sort'] ?? '';
        foreach ($holdings as $holding) {
            $holdingDetails = $this->getHoldingDetailsForItem($holding);
            $nextBatch = [];
            $sortNeeded = false;
            $number = 0;
            $folioItemsForHolding = array_filter(
                $folioItems,
                fn ($item) => $item->queryHoldingsRecordId == $holding->id
            );
            foreach ($folioItemsForHolding as $item) {
                if ($item->discoverySuppress ?? false) {
                    continue;
                }
                $number++;
                $nextItem = $this->processItem($bibId, $holdingDetails, $item, $dueDateItemCount, $number);
                // MSU Start
                // PC-872: Filter out LoM holdings
                if (
                    !empty($nextItem['location']) && (
                        str_starts_with(strtolower($nextItem['location']), 'library of michigan') ||
                        str_starts_with($nextItem['location'], 'Technical migration')
                    )
                ) {
                    continue;
                }
                // MSU End
                if (!empty($vufindItemSort) && !empty($nextItem[$vufindItemSort])) {
                    $sortNeeded = true;
                }
                $nextBatch[] = $nextItem;
            }

            // If there are no item records on this holding, we're going to create a fake one,
            // fill it with data from the FOLIO holdings record, and make it not appear in
            // the full record display using a non-visible AvailabilityStatus.
            if ($number == 0 && $showHoldingsNoItems) {
                $nextBatch[] = $this->buildHoldingsNoItemsData($bibId, $holdingDetails, $holding);
            }
            $items = array_merge(
                $items,
                $sortNeeded
                    ? $this->sortHoldings($nextBatch, $vufindItemSort) : $nextBatch
            );
        }
        // MSU Start
        // Sort by location, enumchron (volume) and copy number
        uasort($items, function ($item1, $item2) {
            return $item2['location'] <=> $item1['location'] ?: // reverse sort
                   version_compare($item1['enumchron'], $item2['enumchron']) ?:
                   $item1['number'] <=> $item2['number'] ?:
                   $item1['id'] <=> $item2['id'];
        });
        // MSU End

        return [
            'total' => count($items),
            'holdings' => $items,
            'electronic_holdings' => [],
        ];
    }

    /**
     * This method queries the ILS for a patron's current checked out items
     *
     * Input: Patron array returned by patronLogin method
     * Output: Returns with a 'count' key (overall result set size) and a 'records'
     *         key (current page of results) containing subarrays representing records
     *         and containing these keys:
     *         duedate - The item's due date (a string).
     *         dueTime - The item's due time (a string, optional).
     *         dueStatus - A special status – may be 'due' (for items due very soon)
     *                     or 'overdue' (for overdue items). (optional).
     *         id - The bibliographic ID of the checked out item.
     *         source - The search backend from which the record may be retrieved
     *                  (optional - defaults to Solr). Introduced in VuFind 2.4.
     *         barcode - The barcode of the item (optional).
     *         renew - The number of times the item has been renewed (optional).
     *         renewLimit - The maximum number of renewals allowed
     *                      (optional - introduced in VuFind 2.3).
     *         request - The number of pending requests for the item (optional).
     *         volume – The volume number of the item (optional).
     *         publication_year – The publication year of the item (optional).
     *         renewable – Whether or not an item is renewable
     *                     (required for renewals).
     *         message – A message regarding the item (optional).
     *         title - The title of the item (optional – only used if the record
     *                                        cannot be found in VuFind's index).
     *         item_id - this is used to match up renew responses and must match
     *                   the item_id in the renew response.
     *         institution_name - Display name of the institution that owns the item.
     *         isbn - An ISBN for use in cover image loading
     *                (optional – introduced in release 2.3)
     *         issn - An ISSN for use in cover image loading
     *                (optional – introduced in release 2.3)
     *         oclc - An OCLC number for use in cover image loading
     *                (optional – introduced in release 2.3)
     *         upc - A UPC for use in cover image loading
     *               (optional – introduced in release 2.3)
     *         borrowingLocation - A string describing the location where the item
     *                         was checked out (optional – introduced in release 2.4)
     *
     * @param array $patron Patron login information from $this->patronLogin
     * @param array $params Additional parameters (limit, page, sort)
     *
     * @return array Transaction data as described above
     */
    public function getMyTransactions($patron, $params = [])
    {
        // MSUL -- overridden to add fields to response
        $limit = $params['limit'] ?? 1000;
        $offset = isset($params['page']) ? ($params['page'] - 1) * $limit : 0;

        $query = 'userId==' . $patron['id'] . ' and status.name==Open';
        if (isset($params['sort'])) {
            $query .= ' sortby ' . $this->escapeCql($params['sort']);
        }
        $resultPage = $this->getResultPage('/circulation/loans', compact('query'), $offset, $limit);
        $transactions = [];
        foreach ($resultPage->loans ?? [] as $trans) {
            $dueStatus = false;
            $date = $this->getDateTimeFromString($trans->dueDate);
            $dueDateTimestamp = $date->getTimestamp();

            $now = time();
            if ($now > $dueDateTimestamp) {
                $dueStatus = 'overdue';
            } elseif ($now > $dueDateTimestamp - (1 * 24 * 60 * 60)) {
                $dueStatus = 'due';
            }
            $transactions[] = [
                'duedate' =>
                    $this->dateConverter->convertToDisplayDate(
                        'U',
                        $dueDateTimestamp
                    ),
                'dueTime' =>
                    $this->dateConverter->convertToDisplayTime(
                        'U',
                        $dueDateTimestamp
                    ),
                'dueStatus' => $dueStatus,
                'id' => $this->getBibId($trans->item->instanceId),
                'item_id' => $trans->item->id,
                'barcode' => $trans->item->barcode,
                'renew' => $trans->renewalCount ?? 0,
                'renewable' => true,
                'title' => $trans->item->title,
                'borrowingLocation' => $trans->item->location?->name ?? null, // MSU
                'volume' => $trans->item->volume ?? null, // MSU
                'callNumber' => $trans->item->callNumber ?? null, // MSU
            ];
        }
        // If we have a full page or have applied an offset, we need to look up the total count of transactions:
        $count = count($transactions);
        if ($offset > 0 || $count >= $limit) {
            // We could use the count in the result page, but that may be an estimate;
            // safer to do a separate lookup to be sure we have the right number!
            $count = $this->getResultCount('/circulation/loans', compact('query'));
        }
        return ['count' => $count, 'records' => $transactions];
    }

    /**
     * Get Pick Up Locations
     *
     * This is responsible get a list of valid locations for holds / recall
     * retrieval
     *
     * @param array $patron   Patron information returned by $this->patronLogin
     * @param array $holdInfo Optional array, only passed in when getting a list
     * in the context of placing or editing a hold. When placing a hold, it contains
     * most of the same values passed to placeHold, minus the patron data. When
     * editing a hold it contains all the hold information returned by getMyHolds.
     * May be used to limit the pickup options or may be ignored. The driver must
     * not add new options to the return array based on this data or other areas of
     * VuFind may behave incorrectly.
     *
     * @return array An array of associative arrays with locationID and
     * locationDisplay keys
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getPickupLocations($patron, $holdInfo = null)
    {
        if ('Delivery' == ($holdInfo['requestGroupId'] ?? null)) {
            $addressTypes = $this->getAddressTypes();
            $limitDeliveryAddressTypes = $this->config['Holds']['limitDeliveryAddressTypes'] ?? [];
            $deliveryPickupLocations = [];
            foreach ($patron['addressTypeIds'] as $addressTypeId) {
                $addressType = $addressTypes[$addressTypeId];
                if (empty($limitDeliveryAddressTypes) || in_array($addressType, $limitDeliveryAddressTypes)) {
                    $deliveryPickupLocations[] = [
                        'locationID' => $addressTypeId,
                        'locationDisplay' => $addressType,
                    ];
                }
            }
            return $deliveryPickupLocations;
        }

        $limitedServicePoints = null;
        if (
            str_contains($this->config['Holds']['limitPickupLocations'] ?? '', 'itemEffectiveLocation')
            // If there's no item ID, it must be a title-level hold,
            // so limiting by itemEffectiveLocation does not apply
            && $holdInfo['item_id'] ?? false
        ) {
            $item = $this->getItemById($holdInfo['item_id']);
            $itemLocationId = $item->effectiveLocationId;
            $limitedServicePoints = $this->getLocationData($itemLocationId)['servicePointIds'];
        }

        // If we have $holdInfo, we can limit ourselves to pickup locations that are valid in context. Because the
        // allowed service point list doesn't include discovery display names, we can't use it directly; we just
        // have to obtain a list of IDs to use as a filter below.
        $legalServicePoints = null;
        if ($holdInfo) {
            $allowed = $this->getAllowedServicePoints(
                $this->getInstanceByBibId($holdInfo['id'])->id,
                $holdInfo['item_id'] ?? null,
                $patron['id']
            );
            if ($allowed !== null) {
                $legalServicePoints = [];
                $preferredRequestType = $this->getPreferredRequestType($holdInfo);
                foreach ($this->getRequestTypeList($preferredRequestType) as $requestType) {
                    foreach ($allowed[$requestType] ?? [] as $servicePoint) {
                        $legalServicePoints[] = $servicePoint['id'];
                    }
                }
            }
        }

        $query = ['query' => 'pickupLocation=true'];
        $locations = [];
        foreach (
            $this->getPagedResults(
                'servicepoints',
                '/service-points',
                $query
            ) as $servicePoint
        ) {
            // MSU -- prevent specific locations by config
            if (!$this->isPickupable($servicePoint->discoveryDisplayName)) {
                continue;
            }
            if ($legalServicePoints !== null && !in_array($servicePoint->id, $legalServicePoints)) {
                continue;
            }
            if ($limitedServicePoints && !in_array($servicePoint->id, $limitedServicePoints)) {
                continue;
            }

            $locations[] = [
                'locationID' => $servicePoint->id,
                'locationDisplay' => $servicePoint->discoveryDisplayName,
            ];
        }

        // MSU START PC-864 Sort the locations, if configured to do so
        // sortby is a list of location names in the order we should be sorting them in
        $sortby = (array)($this->config['Holds']['sortPickupLocations'] ?? []);
        $finalLocations = [];
        foreach ($sortby as $sort) {
            foreach ($locations as $loc) {
                if ($loc['locationDisplay'] == $sort) {
                    $finalLocations[] = $loc;
                    break;
                }
            }
        }
        // Add the rest of the original locations to the final list, if they
        // aren't already included via the previous sort
        foreach ($locations as $loc) {
            if (!in_array($loc['locationDisplay'], $sortby)) {
                $finalLocations[] = $loc;
            }
        }
        // MSU END

        return $finalLocations;
    }

    /**
     * Get request groups
     * MSUL - customized to check if results come back before assuming.
     * This is a good candidate for a PR, but we couldn't find a specific
     * scenario/user that would trigger this.
     *
     * @param int   $bibId       BIB ID
     * @param array $patron      Patron information returned by the patronLogin
     * method.
     * @param array $holdDetails Optional array, only passed in when getting a list
     * in the context of placing a hold; contains most of the same values passed to
     * placeHold, minus the patron data. May be used to limit the request group
     * options or may be ignored.
     *
     * @return array  False if request groups not in use or an array of
     * associative arrays with id and name keys
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getRequestGroups(
        $bibId = null,
        $patron = null,
        $holdDetails = null
    ) {
        // circulation-storage.request-preferences.collection.get
        $response = $this->makeRequest(
            'GET',
            '/request-preference-storage/request-preference?query=userId==' . $patron['id']
        );
        // MSU Start -- Add null checks for $requestPreferences
        $requestPreferencesResponse = json_decode($response->getBody());
        $requestPreferences = $requestPreferencesResponse->requestPreferences[0] ?? null;
        $allowHoldShelf = $requestPreferences?->holdShelf ?? null;
        $allowDelivery = ($requestPreferences?->delivery ?? null) && ($this->config['Holds']['allowDelivery'] ?? true);
        // MSU End
        $locationsLabels = $this->config['Holds']['locationsLabelByRequestGroup'] ?? [];
        if ($allowHoldShelf && $allowDelivery) {
            return [
                [
                    'id' => 'Hold Shelf',
                    'name' => 'fulfillment_method_hold_shelf',
                    'locationsLabel' => $locationsLabels['Hold Shelf'] ?? null,
                ],
                [
                    'id' => 'Delivery',
                    'name' => 'fulfillment_method_delivery',
                    'locationsLabel' => $locationsLabels['Delivery'] ?? null,
                ],
            ];
        }
        return false;
    }

    /**
     * This method queries the ILS for a patron's current holds
     *
     * Input: Patron array returned by patronLogin method
     * Output: Returns an array of associative arrays, one for each hold associated
     * with the specified account. Each associative array contains these keys:
     *     type - A string describing the type of hold – i.e. hold vs. recall
     * (optional).
     *     id - The bibliographic record ID associated with the hold (optional).
     *     source - The search backend from which the record may be retrieved
     * (optional - defaults to Solr). Introduced in VuFind 2.4.
     *     location - A string describing the pickup location for the held item
     * (optional). In VuFind 1.2, this should correspond with a locationID value from
     * getPickUpLocations. In VuFind 1.3 and later, it may be either
     * a locationID value or a raw ready-to-display string.
     *     reqnum - A control number for the request (optional).
     *     expire - The expiration date of the hold (a string).
     *     create - The creation date of the hold (a string).
     *     position – The position of the user in the holds queue (optional)
     *     available – Whether or not the hold is available (true/false) (optional)
     *     item_id – The item id the request item (optional).
     *     volume – The volume number of the item (optional)
     *     publication_year – The publication year of the item (optional)
     *     title - The title of the item
     * (optional – only used if the record cannot be found in VuFind's index).
     *     isbn - An ISBN for use in cover image loading (optional)
     *     issn - An ISSN for use in cover image loading (optional)
     *     oclc - An OCLC number for use in cover image loading (optional)
     *     upc - A UPC for use in cover image loading (optional)
     *     cancel_details - The cancel token, or a blank string if cancel is illegal
     * for this hold; if omitted, this will be dynamically generated using
     * getCancelHoldDetails(). You should only fill this in if it is more efficient
     * to calculate the value up front; if it is an expensive calculation, you should
     * omit the value entirely and let getCancelHoldDetails() do its job on demand.
     * This optional feature was introduced in release 3.1.
     *
     * @param array $patron Patron login information from $this->patronLogin
     *
     * @return array Associative array of holds information
     */
    public function getMyHolds($patron)
    {
        $userQuery = '(requesterId == "' . $patron['id'] . '" '
            . 'or proxyUserId == "' . $patron['id'] . '")';
        $query = [
            // MSU customization: sorting
            'query' => '(' . $userQuery . ' and status == Open*) '
            . 'sortBy requestDate/sort.ascending title/sort.ascending',
        ];
        $holds = [];
        // MSU customization: allowCancelingAvailableRequests
        $allowCancelingAvailableRequests
            = $this->config['Holds']['allowCancelingAvailableRequests'] ?? true;
        foreach (
            $this->getPagedResults(
                'requests',
                '/request-storage/requests',
                $query
            ) as $hold
        ) {
            $requestDate = $this->dateConverter->convertToDisplayDate(
                'Y-m-d H:i',
                $hold->requestDate
            );
            // Set expire date if it was included in the response
            $expireDate = isset($hold->requestExpirationDate)
                ? $this->dateConverter->convertToDisplayDate(
                    'Y-m-d H:i',
                    $hold->requestExpirationDate
                )
                : null;
            // Set lastPickup Date if provided, format to j M Y
            $lastPickup = isset($hold->holdShelfExpirationDate)
                ? $this->dateConverter->convertToDisplayDate(
                    'Y-m-d H:i',
                    $hold->holdShelfExpirationDate
                )
                : null;
            // MSU START
            $request_id = $this->getBibId(
                $hold->instanceId,
                $hold->holdingsRecordId ?? null,
                $hold->itemId ?? null
            );
            $available = in_array(
                $hold->status,
                $this->config['Holds']['available']
                ?? $this->defaultAvailabilityStatuses
            );
            $servicePoint = isset($hold->pickupServicePointId)
                ? $this->getPickupLocation($hold->pickupServicePointId) : null;
            $location = isset($servicePoint) && count($servicePoint) == 1
                ? $servicePoint[0]['locationDisplay'] : '';
            $updateDetails = (!$available || $allowCancelingAvailableRequests)
                ? (string)$request_id : '';
            // MSU END
            $currentHold = [
                'type' => $hold->requestType,
                'create' => $requestDate,
                'expire' => $expireDate ?? '',
                'id' => $request_id, // MSU -- use variable since it's used in updateDetails
                'item_id' => $hold->itemId ?? null,
                'reqnum' => $hold->id,
                // Title moved from item to instance in Lotus release:
                'title' => $hold->instance->title ?? $hold->item->title ?? '',
                'available' => $available, // MSU -- use variable since it's used in updateDetails
                'in_transit' => in_array(
                    $hold->status,
                    $this->config['Holds']['in_transit']
                    ?? $this->defaultInTransitStatuses
                ),
                'last_pickup_date' => $lastPickup,
                'position' => $hold->position ?? null,
                // MSU customization: fields added:
                'processed' => $hold->status !== 'Open - Not yet filled',
                'location' => $location,
                'updateDetails' => $updateDetails,
                'status' => $hold->status,
            ];
            // If this request was created by a proxy user, and the proxy user
            // is not the current user, we need to indicate their name.
            if (
                ($hold->proxyUserId ?? $patron['id']) !== $patron['id']
                && isset($hold->proxy)
            ) {
                $currentHold['proxiedBy']
                    = $this->userObjectToNameString($hold->proxy);
            }
            // If this request was not created for the current user, it must be
            // a proxy request created by the current user. We should indicate this.
            if (
                ($hold->requesterId ?? $patron['id']) !== $patron['id']
                && isset($hold->requester)
            ) {
                $currentHold['proxiedFor']
                    = $this->userObjectToNameString($hold->requester);
            }
            $holds[] = $currentHold;
        }
        return $holds;
    }

    /**
     * Find Reserves
     *
     * Obtain information on course reserves.
     *
     * @param string $course ID from getCourses (empty string to match all)
     * @param string $inst   ID from getInstructors (empty string to match all)
     * @param string $dept   ID from getDepartments (empty string to match all)
     *
     * @return mixed An array of associative arrays representing reserve items.
     */
    public function findReserves($course, $inst, $dept)
    {
        $retVal = [];
        $query = [];
        $legalCourses = $this->getCourses();

        $includeSuppressed = $this->config['CourseReserves']['includeSuppressed'] ?? false;

        if (!$includeSuppressed) {
            $query = [
                'query' => 'copiedItem.instanceDiscoverySuppress==false',
            ];
        }

        // Results can be paginated, so let's loop until we've gotten everything:
        foreach (
            $this->getPagedResults(
                'reserves',
                '/coursereserves/reserves',
                $query
            ) as $item
        ) {
            // MSU customization to always use instanceId so that we can have getBibId lookup
            // the correct prefix
            $instanceId = $item->copiedItem->instanceId ?? null;
            $bibId = $this->getBibId($instanceId);

            // MSU customization - Get the electronic access links from the item record if possible
            // electronicAccess will be an array with keys: uri, linkText, publicNote, relationshipId
            $itemId = $item->itemId ?? null;
            $electronicAccess = null;
            $urlPattern = '/https?:\/\/catalog\.lib\.msu\.edu\/Record\/([.a-zA-Z0-9]+)/i';
            if ($itemId !== null) {
                $links = $this->getElectronicAccessLinks($itemId) ?? [];
                foreach ($links as $link) {
                    if ($link->uri !== null && preg_match($urlPattern, $link->uri, $matches) && count($matches) > 1) {
                        $bibId = $matches[1]; // this gives us the VuFind ID with the prefix it has in the Biblio index
                        break;
                    }
                }
            }

            if ($bibId !== null) {
                $courseData = $this->getCourseDetails(
                    $item->courseListingId ?? null
                );
                $instructorIds = $this->getInstructorIds(
                    $item->courseListingId ?? null
                );
                foreach ($courseData as $courseId => $departmentId) {
                    // If the present course ID is not in the legal course list, it is likely
                    // expired data and should be skipped.
                    if (!isset($legalCourses[$courseId])) {
                        continue;
                    }
                    foreach ($instructorIds as $instructorId) {
                        $retVal[] = [
                            'BIB_ID' => $bibId,
                            'COURSE_ID' => $courseId == '' ? null : $courseId,
                            'DEPARTMENT_ID' => $departmentId == ''
                                ? null : $departmentId,
                            'INSTRUCTOR_ID' => $instructorId,
                        ];
                    }
                }
            }
        }

        // If the user has requested a filter, apply it now:
        if (!empty($course) || !empty($inst) || !empty($dept)) {
            $filter = function ($value) use ($course, $inst, $dept) {
                return (empty($course) || $course == $value['COURSE_ID'])
                    && (empty($inst) || $inst == $value['INSTRUCTOR_ID'])
                    && (empty($dept) || $dept == $value['DEPARTMENT_ID']);
            };
            return array_filter($retVal, $filter);
        }
        return $retVal;
    }
 
    /**
     * MSUL-only function
     * Determine if the provided pickup service point is excluded or not
     * based on the configurations set.
     *
     * TODO -- This is nearly identical to isHoldable. Would it be a terrible
     * idea to add an optional extra parameter to that function to be able to
     * merge this in with that one?
     *
     * @param string $servicepoint servicepoint discover display name from
     * getPickupLocations
     *
     * @return bool
     */
    public function isPickupable($servicepoint)
    {
        $mode = $this->config['Holds']['excludePickupLocationsCompareMode'] ?? 'exact';
        $excludeLocs = (array)($this->config['Holds']['excludePickupLocations'] ?? []);

        // Exclude checking by regex match
        if (trim(strtolower($mode)) == 'regex') {
            foreach ($excludeLocs as $pattern) {
                $match = @preg_match($pattern, $servicepoint);
                // Invalid regex, skip this pattern
                if ($match === false) {
                    $this->logWarning(
                        'Invalid regex found in excludePickupLocations: ' .
                        $pattern
                    );
                    continue;
                }
                if ($match === 1) {
                    return false;
                }
            }
            return true;
        }
        // Otherwise exclude checking by exact match
        return !in_array($servicepoint, $excludeLocs);
    }

    /**
     * MSUL-only function
     * Get the location record for the specified location
     *
     * @param string $locationId location identifier
     *
     * @return array of location data
     */
    public function getPickupLocation($locationId)
    {
        $query = ['query' => 'id == "' . $locationId . '"  '];
        $locations = [];
        foreach (
            $this->getPagedResults(
                'servicepoints',
                '/service-points',
                $query
            ) as $servicepoint
        ) {
            $locations[] = [
                'locationID' => $servicepoint->id,
                'locationDisplay' => $servicepoint->discoveryDisplayName,
            ];
        }
        return $locations;
    }

    /**
     * MSUL-only function
     * Get the license agreement data for the specific publisher
     *
     * @param string $publisherName Publisher name
     *
     * @return array of license agreement data
     */
    public function getLicenseAgreement($publisherName)
    {
        // Call the package API to get the `id` field
        $query = [
            'q' => '"' . $publisherName . '"',
            'page' => '1',
            'filter[selected]' => 'true',
        ];
        $headers = [
            'Accept: application/vnd.api+json',
        ];
        $response = $this->makeRequest('GET', '/eholdings/packages', $query, $headers);
        $packages = json_decode($response->getBody());
        $packageCount = count($packages->data);
        if ($packageCount === 0) {
            $this->debug('No package for publisher');
            return [];
        } elseif ($packageCount > 1) {
            $this->debug($packageCount . ' packages return for publisher, looking for an exact match');
            for ($i = 0; $i < $packageCount; $i++) {
                if (
                    isset($packages->data[$i]->attributes->name)
                    && isset($packages->data[$i]->id)
                ) {
                    if ($packages->data[$i]->attributes->name === $publisherName) {
                        $packageId = $packages->data[$i]->id;
                        $this->debug('Found one at index ' . $i);
                        break;
                    } elseif (!isset($tmpPackageId)) {
                        // Assuming it's better to return one of any package than throwing an exception
                        // Get the first package id available even if not matching the publisher name
                        $tmpPackageId = $packages->data[$i]->id;
                    }
                }
            }
            if (!isset($packageId)) {
                if (isset($tmpPackageId)) {
                    $packageId = $tmpPackageId;
                    $this->warning('Could not identify the correct package among several publishers, ' .
                        'selected the first found (' . $publisherName . ')');
                } else {
                    throw new ILSException('Could not identify single package for publisher');
                }
            }
        } elseif (isset($packages->data[0]->id)) {
            $packageId = $packages->data[0]->id;
        } else {
            $this->debug('Unable to get publisher id in package');
            return [];
        }
        // Get the license agreements if we were able to locate the package ID
        $query = [
            'referenceId' => $packageId,
        ];
        $response = $this->makeRequest('GET', '/erm/sas/publicLookup', $query);
        $licenses = json_decode($response->getBody());
        // Get the license agreement data for the record if there was one found
        $licenseRecords = $licenses->records;
        if (count($licenseRecords) == 0) {
            $this->debug('Unable to get records from licenses (no license record) - packageId: ' . $packageId);
            return [];
        }
        $linkedLicenses = $licenseRecords[0]->linkedLicenses;
        if (count($linkedLicenses) == 0) {
            $this->debug('Unable to get records from licenses (no linked license) - packageId: ' . $packageId);
            return [];
        }
        $linkedLicense = $linkedLicenses[0];
        if (isset($linkedLicense->error)) {
            if (isset($linkedLicense->message)) {
                $message = ' - message: ' . $linkedLicense->message;
            } else {
                $message = '';
            }
            $this->logError('Error getting records from licenses (FOLIO error) - packageId: ' . $packageId . $message);
            return [];
        }
        if (!isset($linkedLicense->remoteId_object)) {
            $this->debug('Unable to get records from licenses (no remoteId object) - packageId: ' . $packageId);
            return [];
        }
        $customProperties = $linkedLicense->remoteId_object?->customProperties;

        $licenseAgreement = [];
        if (isset($customProperties->vendoraccessibilityinfo[0]->value)) {
            $licenseAgreement['vendoraccessibilityinfo'] = $customProperties->vendoraccessibilityinfo[0]->value;
        }
        if (isset($customProperties->authorizedusers[0]->value->label)) {
            $licenseAgreement['authorizedusers'] = $customProperties->authorizedusers[0]->value->label;
        }
        if (isset($customProperties->ConcurrentUsers[0]->value)) {
            $licenseAgreement['ConcurrentUsers'] = $customProperties->ConcurrentUsers[0]->value;
        }
        return $licenseAgreement;
    }

    /**
     * MSUL-only function
     * Retrieve the electronic access data from the item records
     *
     * @param string $itemId itemId from holdings data
     *
     * @return array associative array of the link data
     */
    protected function getElectronicAccessLinks($itemId)
    {
        try {
            $response = $this->makeRequest(
                'GET',
                '/item-storage/items/' . $itemId,
                allowedFailureCodes: [404]
            );
            if ($response && $response->getStatusCode() != 404) {
                $item = json_decode($response->getBody());
                return $item->electronicAccess;
            }
            return [];
        } catch (ILSException $e) {
            return [];
        }
    }

    /**
     * MSUL-only function
     * Get the timeout for external API calls
     * MSUL PC-1416 Added to support external API calls
     * If this is ever added to VF core, likely just move
     * this setting to config.ini.
     *
     * @return int
     */
    protected function getExternalTimeout()
    {
        $msulConfig = $this->configReader->get('msul');

        if (isset($msulConfig)) {
            return $msulConfig['Locations']['timeout'] ?? 2;
        }

        return 2;
    }

    /**
     * MSUL-only function
     * Make external API requests
     * MSUL PC-1416 Added to support external API calls
     * If ever made into a PR, likely have makeRequest call this function
     * to avoid code duplication.
     *
     * @param string            $method              GET/POST/PUT/DELETE/etc
     * @param string            $url                 API URL
     * @param string|array      $params              Query parameters
     * @param array             $headers             Additional headers
     * @param true|int[]|string $allowedFailureCodes HTTP failure codes that should
     * NOT cause an ILSException to be thrown. May be an array of integers, a regular
     * expression, or boolean true to allow all codes.
     * @param string|array      $debugParams         Value to use in place of $params
     * in debug messages (useful for concealing sensitive data, etc.)
     * @param int               $attemptNumber       Counter to keep track of attempts
     * (starts at 1 for the first attempt)
     *
     * @return \Laminas\Http\Response
     * @throws ILSException
     */
    public function makeExternalRequest(
        $method = 'GET',
        $url = '',
        $params = [],
        $headers = [],
        $allowedFailureCodes = [],
        $debugParams = null,
        $attemptNumber = 1
    ) {
        $client = $this->httpService->createClient(
            $url,
            $method,
            120
        );

        // MSUL -- Set timeout
        $client->setOptions(['timeout' => $this->getExternalTimeout()]);

        // Add default headers and parameters
        $req_headers = $client->getRequest()->getHeaders();
        $req_headers->addHeaders($headers);
        [$req_headers, $params] = $this->preRequest($req_headers, $params);

        if ($this->logger) {
            $this->debugRequest($method, $url, $debugParams ?? $params, $req_headers);
        }

        // Add params
        if ($method == 'GET') {
            $client->setParameterGet($params);
        } else {
            if (is_string($params)) {
                $client->getRequest()->setContent($params);
            } else {
                $client->setParameterPost($params);
            }
        }
        $startTime = microtime(true);
        try {
            $response = $client->send();
        } catch (\Exception $e) {
            $this->logError('Unexpected ' . $e::class . ': ' . (string)$e);
            throw new ILSException('Error during send operation.');
        }
        $endTime = microtime(true);
        $responseTime = $endTime - $startTime;
        $this->debug('Request Response Time --- ' . $responseTime . ' seconds. ' . $url);
        $code = $response->getStatusCode();
        if (
            !$response->isSuccess()
            && !$this->failureCodeIsAllowed($code, $allowedFailureCodes)
        ) {
            $this->logError(
                "Unexpected error response (attempt #$attemptNumber"
                . "); code: {$response->getStatusCode()}, body: {$response->getBody()}"
            );
            if ($this->shouldRetryAfterUnexpectedStatusCode($response, $attemptNumber)) {
                return $this->makeExternalRequest(
                    $method,
                    $url,
                    $params,
                    $headers,
                    $allowedFailureCodes,
                    $debugParams,
                    $attemptNumber + 1
                );
            } else {
                throw new ILSException('Unexpected error code.');
            }
        }
        return $response;
    }

    /**
     * Make requests
     * MSUL Override to update default headers instead of just add to them PC-606
     * Overridden from AbstractAPI
     *
     * @param string            $method              GET/POST/PUT/DELETE/etc
     * @param string            $path                API path (with a leading /)
     * @param string|array      $params              Query parameters
     * @param array             $headers             Additional headers
     * @param true|int[]|string $allowedFailureCodes HTTP failure codes that should
     * NOT cause an ILSException to be thrown. May be an array of integers, a regular
     * expression, or boolean true to allow all codes.
     * @param string|array      $debugParams         Value to use in place of $params
     * in debug messages (useful for concealing sensitive data, etc.)
     * @param int               $attemptNumber       Counter to keep track of attempts
     * (starts at 1 for the first attempt)
     *
     * @return \Laminas\Http\Response
     * @throws ILSException
     */
    public function makeRequest(
        $method = 'GET',
        $path = '/',
        $params = [],
        $headers = [],
        $allowedFailureCodes = [],
        $debugParams = null,
        $attemptNumber = 1
    ) {
        $client = $this->httpService->createClient(
            $this->config['API']['base_url'] . $path,
            $method,
            120
        );

        // MSUL customization -- Update default headers and parameters when they exist
        $req_headers = $client->getRequest()->getHeaders();
        [$req_headers, $params] = $this->preRequest($req_headers, $params);
        if (!empty($headers)) {
            foreach ($headers as $header) {
                $matches = $req_headers->get(explode(':', $header)[0]);

                if ($matches instanceof ArrayIterator) {
                    foreach ($req_headers as $req_header) {
                        $req_headers->removeHeader($req_header);
                    }
                } elseif ($matches instanceof HeaderInterface) {
                    $req_headers->removeHeader($matches);
                }
                if ($matches != false) {
                    $req_headers->addHeaderLine($header);
                }
            }
        }

        if ($this->logger) {
            $this->debugRequest($method, $path, $debugParams ?? $params, $req_headers);
        }

        // Add params
        if ($method == 'GET') {
            $client->setParameterGet($params);
        } else {
            if (is_string($params)) {
                $client->getRequest()->setContent($params);
            } else {
                $client->setParameterPost($params);
            }
        }
        $startTime = microtime(true);
        try {
            $response = $client->send();
        } catch (\Exception $e) {
            $this->logError('Unexpected ' . $e::class . ': ' . (string)$e);
            throw new ILSException('Error during send operation.');
        }
        $code = $response->getStatusCode();
        $endTime = microtime(true);
        $responseTime = $endTime - $startTime;
        $this->debug(
            'Request Response Time --- ' . $responseTime . ' seconds. ' . $path . ' [' . $code . ']'
        );
        if (
            !$response->isSuccess()
            && !$this->failureCodeIsAllowed($code, $allowedFailureCodes)
        ) {
            $this->logError(
                "Unexpected error response (attempt #{$attemptNumber}); "
                . "code: {$response->getStatusCode()}, request: {$method} {$path}, "
                . "body: {$response->getBody()}"
            );
            if ($this->shouldRetryAfterUnexpectedStatusCode($response, $attemptNumber)) {
                return $this->makeRequest(
                    $method,
                    $path,
                    $params,
                    $headers,
                    $allowedFailureCodes,
                    $debugParams,
                    $attemptNumber + 1
                );
            } else {
                throw new ILSException('Unexpected error code.');
            }
        }
        if ($jsonLog = ($this->config['API']['json_log_file'] ?? false)) {
            if (APPLICATION_ENV !== 'development') {
                $this->logError(
                    'SECURITY: json_log_file enabled outside of development mode; disabling feature.'
                );
            } else {
                $body = $response->getBody();
                $jsonBody = @json_decode($body);
                $json = file_exists($jsonLog)
                    ? json_decode(file_get_contents($jsonLog)) : [];
                $json[] = [
                    'expectedMethod' => $method,
                    'expectedPath' => $path,
                    'expectedParams' => $params,
                    'body' => $jsonBody ? $jsonBody : $body,
                    'bodyType' => $jsonBody ? 'json' : 'string',
                    'status' => $code,
                ];
                file_put_contents($jsonLog, json_encode($json));
            }
        }
        return $response;
    }
}
