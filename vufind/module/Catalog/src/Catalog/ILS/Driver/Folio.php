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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
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
use function func_num_args;
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
    protected const QUERY_BY_IDS_BATCH_SIZE = 20;

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
     * Initialize the driver.
     *
     * Check or renew our auth token
     *
     * @return void
     */
    public function init()
    {
        $factory = $this->sessionFactory;
        $this->sessionCache = $factory($this->tenant);
        $cacheType = 'session';
        if ($this->useGlobalTokenCache()) {
            $globalTokenData = (array)($this->getCachedData('token') ?? []);
            if (count($globalTokenData) === 2) {
                $cacheType = 'global';
                [$this->sessionCache->folio_token, $this->sessionCache->folio_token_expiration] = $globalTokenData;
            }
        }
        if ($this->sessionCache->folio_token ?? false) {
            $this->token = $this->sessionCache->folio_token;
            $this->tokenExpiration = $this->sessionCache->folio_token_expiration ?? null;
            $this->debug(
                'Token taken from ' . $cacheType . ' cache: ' . substr($this->token, 0, 30) . '...'
            );
        }
        // MSU: added try/catch
        try {
            if ($this->token == null) {
                $this->renewTenantToken();
            } else {
                $this->checkTenantToken();
            }
        } catch (\Exception $e) {
            // Errors in init() should not be fatal, it could prevent using EDS when FOLIO fails
            $this->token = $this->tokenExpiration = null;
            $this->logError("Failed to get a token to initialize the FOLIO driver: " . $e->getMessage());
        }
    }

    /**
     * Given an instance object or identifer, or a holding or item identifier,
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
     * Get FOLIO records by batches of ids
     *
     * @param string[] $ids         ids to look for in the records
     * @param string   $idField     field to compare to given ids
     * @param string   $responseKey response key with the records to retrieve
     * @param string   $endpoint    FOLIO API endpoint
     * @param string   $querySuffix optional string to append to the queries
     *
     * @return \Generator<object>
     * @throws ILSException if there is an issue with the FOLIO response
     */
    protected function getByBatch($ids, $idField, $responseKey, $endpoint, $querySuffix = '')
    {
        if (count($ids) == 0) {
            return;
        }
        $idToKey = fn ($id) => $endpoint . '[' . $idField . '=' . $id . ']';
        $idsToLookFor = [];
        foreach ($ids as $id) {
            $items = $this->getCachedData($idToKey($id));
            if ($items == null) {
                $idsToLookFor[] = $id;
            } else {
                foreach ($items as $item) {
                    yield $item;
                }
            }
        }
        $resultsToCache = [];
        foreach (array_chunk($idsToLookFor, self::QUERY_BY_IDS_BATCH_SIZE) as $idsInBatch) {
            $idsWithQuotes = array_map(fn ($id) => '"' . $this->escapeCql($id) . '"', $idsInBatch);
            $query = [
                'query' => $idField . ' == (' . implode(' OR ', $idsWithQuotes) . ')' . $querySuffix,
            ];
            foreach (
                $this->getPagedResults(
                    $responseKey,
                    $endpoint,
                    $query
                ) as $item
            ) {
                $key = $idToKey($item->$idField);
                if (isset($resultsToCache[$key])) {
                    $resultsToCache[$key][] = $item;
                } else {
                    $resultsToCache[$key] = [ $item ];
                }
                yield $item;
            }
        }
        foreach ($resultsToCache as $key => $items) {
            $this->putCachedData($key, $items);
        }
    }

    /**
     * Support method for getHoldings() -- retrieve holdings by instance ids
     *
     * @param string[] $instanceIds the FOLIO instance ids
     *
     * @return object[]
     * @throws ILSException if there is an issue with the FOLIO response
     */
    protected function getHoldingsByInstanceIds(array $instanceIds)
    {
        if (count($instanceIds) == 0) {
            return [];
        }
        $holdings = [];
        $querySuffix = ' NOT discoverySuppress==true';
        foreach (
            $this->getByBatch(
                $instanceIds,
                'instanceId',
                'holdingsRecords',
                '/holdings-storage/holdings',
                $querySuffix
            ) as $holding
        ) {
            $holdings[] = $holding;
        }
        return $holdings;
    }

    /**
     * Support method for getHoldings() -- retrieve items by holding ids (including bound-with items)
     *
     * @param string[] $holdingIds the FOLIO holdings ids
     *
     * @return object[] The items, with an additional queryHoldingsRecordId property with the matching holdings id
     * @throws ILSException if there is an issue with the FOLIO response
     */
    protected function getItemsByHoldingIds(array $holdingIds)
    {
        if (count($holdingIds) == 0) {
            return [];
        }
        $items = [];
        $folioItemSort = $this->config['Holdings']['folio_sort'] ?? '';
        if (!empty($folioItemSort)) {
            $querySuffix = ' sortby ' . $folioItemSort;
        } else {
            $querySuffix = '';
        }
        if (count($holdingIds) == 1) {
            // /inventory/items-by-holdings-id returns bound-with items too (but it only takes one holdingsRecordId)
            foreach (
                $this->getByBatch(
                    $holdingIds,
                    'holdingsRecordId',
                    'items',
                    '/inventory/items-by-holdings-id',
                    $querySuffix
                ) as $item
            ) {
                $item->queryHoldingsRecordId = $holdingIds[0];
                $items[] = $item;
            }
            return $items;
        }
        // /inventory/items does not return bound-with items, we have to retrieve them afterwards
        foreach (
            $this->getByBatch(
                $holdingIds,
                'holdingsRecordId',
                'items',
                '/inventory/items',
                $querySuffix
            ) as $item
        ) {
            $item->queryHoldingsRecordId = $item->holdingsRecordId;
            $items[] = $item;
        }
        $boundWithItemIds = [];
        $itemIdToHoldingsRecordId = [];
        foreach (
            $this->getByBatch(
                $holdingIds,
                'holdingsRecordId',
                'boundWithParts',
                '/inventory-storage/bound-with-parts',
                $querySuffix
            ) as $boundWithPart
        ) {
            $boundWithItemIds[] = $boundWithPart->itemId;
            $itemIdToHoldingsRecordId[$boundWithPart->itemId] = $boundWithPart->holdingsRecordId;
        }
        foreach (
            $this->getByBatch(
                $boundWithItemIds,
                'id',
                'items',
                '/inventory/items'
            ) as $item
        ) {
            $item->queryHoldingsRecordId = $itemIdToHoldingsRecordId[$item->id];
            $items[] = $item;
        }
        return $items;
    }

    /**
     * Retrieve FOLIO instances using VuFind's chosen bibliographic identifiers.
     *
     * @param string[] $bibIds Bib-level ids
     *
     * @return object[]
     * @throws ILSException if there is an issue with the FOLIO response or an instance is not found
     */
    protected function getInstancesByBibIds($bibIds)
    {
        // Figure out which ID type to use in the CQL query; if the user configured
        // instance IDs, use the 'id' field, otherwise pass the setting through
        // directly:
        $idType = $this->getBibIdType();
        $idField = $idType === 'instance' ? 'id' : $idType;
        $instances = [];
        foreach (
            $this->getByBatch(
                $bibIds,
                $idField,
                'instances',
                '/instance-storage/instances'
            ) as $instance
        ) {
            $instances[] = $instance;
        }
        if (count($instances) != count($bibIds)) {
            throw new ILSException('An instance was not found, bibIds=' . implode(',', $bibIds));
        }
        return $instances;
    }

    /**
     * Retrieve FOLIO instance using VuFind's chosen bibliographic identifier.
     *
     * @param string $bibId Bib-level id
     *
     * @return object
     * @throws ILSException if there is an issue with the FOLIO response or the instance is not found
     */
    public function getInstanceByBibId($bibId)
    {
        // MSUL override to make publicly available to reserve index command
        // NOTE: getInstancesByBibIds() throws an exception if there is no instance matching bibId
        return $this->getInstancesByBibIds([$bibId])[0];
    }

    /**
     * Return statuses for an array of bibIds, optimizing retrieval with bulk calls
     *
     * @param string[] $idList array of bibIds
     *
     * @return array[] the items for each bibId (in the given order)
     * @throws ILSException if there is an issue with a FOLIO response or an instance is not found
     */
    public function getStatuses($idList)
    {
        $holdings = $this->getHoldings($idList);
        return array_map(fn ($holding) => $holding['holdings'], $holdings);
    }

    /**
     * Support method for getHoldings() -- given a loan type ID return the string name for it
     *
     * @param string|null $loanTypeId Loan Type ID (ie: the value of permanentLoanTypeId)
     *
     * @return string|null
     * @throws ILSException
     */
    protected function getLoanType(string $loanTypeId = null): ?string
    {
        $loanType = null;

        // Make sure a value was passed
        if (empty($loanTypeId)) {
            return $loanType;
        }

        // Query the loan type by the ID
        $query = [
            'query' => 'id=="' . $loanTypeId . '"',
        ];
        foreach (
            $this->getPagedResults(
                'loantypes',
                '/loan-types',
                $query
            ) as $loanType
        ) {
            // There should only be one result
            $loanType = $loanType->name;
            break;
        }

        return $loanType;
    }

    /**
     * Support method for getHoldings() -- given a few key details, format an item
     * for inclusion in the return value.
     *
     * @param string     $bibId            Current bibliographic ID
     * @param array      $holdingDetails   Holding details produced by
     *                                 getHoldingDetailsForItem()
     * @param object     $item             FOLIO item record (decoded from JSON)
     * @param int        $number           The current item number (position within
     *                                 current holdings record)
     * @param string     $dueDateValue     The due date to display to the user
     * @param array      $boundWithRecords Any bib records this holding is bound with
     * @param ?\stdClass $currentLoan      Any current loan on this item
     * @param string     $tempLoanType     The temporary loan type for the item
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
        $currentLoan,
        string $tempLoanType = null
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
        $locAndHoldings = $this->getItemFieldsFromNonItemData($locationId, $holdingDetails, $currentLoan);
        $callNumberData = $this->chooseCallNumber(
            $holdingDetails['holdingCallNumberPrefix'],
            $holdingDetails['holdingCallNumber'],
            $item->effectiveCallNumberComponents->prefix
                ?? $item->itemLevelCallNumberPrefix ?? '',
            $item->effectiveCallNumberComponents->callNumber
                ?? $item->itemLevelCallNumber ?? ''
        );

        // MSU START
        // PC-835: Items with loan type "Non Circulating" should show as "Lib Use Only" after they're checked in
        if (
            ($item->permanentLoanType->id ?? '') == 'adac93ac-951f-4f42-ab32-79f4faeabb50' &&
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
            'issues' => $holdingDetails['holdingsStatements'], // MSU
            'electronic_access' => $item->electronicAccess, // MSU
            'temporary_loan_type' => $tempLoanType, // MSU
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
     *
     * @return array An associative array
     */
    protected function processItem($bibId, $holdingDetails, $item, &$dueDateItemCount)
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
        // MSU - PC-930: Add Loan Type to results
        $tempLoanType = $this->getLoanType($item->temporaryLoanTypeId ?? null);
        $nextItem = $this->formatHoldingItem(
            $bibId,
            $holdingDetails,
            $item,
            $copyNumber,
            $dueDateValue,
            $boundWithRecords ?? [],
            $currentLoan,
            $tempLoanType // MSU
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
                $nextItem = $this->processItem($bibId, $holdingDetails, $item, $dueDateItemCount);
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
                $locAndHoldings = $this->getItemFieldsFromNonItemData($holding->effectiveLocationId, $holdingDetails);
                $invisibleAvailabilityStatus = new AvailabilityStatus(
                    true,
                    'HoldingStatus::holding_no_items_availability_message'
                );
                $invisibleAvailabilityStatus->setVisibilityInHoldings(false);
                $nextBatch[] = $locAndHoldings + [
                    'id' => $bibId,
                    'callnumber' => $holdingDetails['holdingCallNumber'],
                    'callnumber_prefix' => $holdingDetails['holdingCallNumberPrefix'],
                    'reserve' => 'N',
                    'availability' => $invisibleAvailabilityStatus,
                ];
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
     * Query the ILS for information about a single holdings.
     *
     * @param string $bibId   Bib-level id
     * @param array  $patron  Patron login information from $this->patronLogin
     * @param array  $options Extra options (not currently used)
     *
     * @return array An associative array with the keys: total, holdings, electronic_holdings
     * @throws ILSException if there is an issue with a FOLIO response or the instance is not found
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getHolding($bibId, array $patron = null, array $options = [])
    {
        // NOTE: getHoldings() always returns something for a bibId, unless the instance is not found.
        // If the instance is not found, an ILSException is thrown.
        return $this->getHoldings([$bibId])[0];
    }

    /**
     * Query the ILS for holdings information.
     *
     * @param string[] $bibIds Bib-level ids
     *
     * @return array[] An array of associative arrays, one for each bibId
     * @throws ILSException if there is an issue with a FOLIO response or an instance is not found
     */
    public function getHoldings($bibIds)
    {
        $idType = $this->getBibIdType();
        $bibIdToInstanceId = [];
        if ($idType === 'instance') {
            // Do not retrieve the instances if we already have their ids
            $instanceIds = $bibIds;
            foreach ($bibIds as $bibId) {
                $bibIdToInstanceId[$bibId] = $bibId;
            }
        } else {
            $instances = $this->getInstancesByBibIds($bibIds);
            $instanceIds = array_map(fn ($instance) => $instance->id, $instances);
            foreach ($instances as $instance) {
                $bibIdToInstanceId[$instance->$idType] = $instance->id;
            }
        }
        $holdings = $this->getHoldingsByInstanceIds($instanceIds);
        $holdingIds = array_map(fn ($holding) => $holding->id, $holdings);
        if (count($holdings) == 0) {
            $folioItems = [];
        } else {
            $folioItems = $this->getItemsByHoldingIds($holdingIds);
        }
        $results = [];
        foreach ($bibIds as $bibId) {
            $instanceId = $bibIdToInstanceId[$bibId];
            $holdingsForInstance = array_filter($holdings, fn ($holding) => $holding->instanceId == $instanceId);
            $results[] = $this->processInstanceHoldings($bibId, $holdingsForInstance, $folioItems);
        }
        return $results;
    }

    /**
     * This method queries the ILS for a patron's current checked out items
     *
     * Input: Patron array returned by patronLogin method
     * Output: Returns an array of associative arrays.
     *         Each associative array contains these keys:
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
            // MSUL customization to add item_id param for PC-1405
            $allowed = $this->getAllowedServicePoints(
                $this->getInstanceByBibId($holdInfo['id'])->id,
                $patron['id'],
                'create',
                $holdInfo['item_id']
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
     * Make requests
     * MSUL Override to update default headers instead of just add to them PC-606
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

    /**
     * MSUL PC-1405 Use itemId or instanceId based on if title level holds is enabled
     * Get allowed service points for a request. Returns null if data cannot be obtained.
     *
     * @param string $instanceId  Instance UUID being requested
     * @param string $requesterId Patron UUID placing request
     * @param string $operation   Operation type (default = create)
     *
     * @return ?array
     */
    public function getAllowedServicePoints(
        string $instanceId,
        string $requesterId,
        string $operation = 'create'
    ): ?array {
        try {
            // This won't be required in a PR -- doing it this way here
            // since we can't change the function signature to add a new
            // parameter directly
            $itemId = null;
            if (func_num_args() >= 4) {
                $itemId = func_get_arg(3);
            }
            // circulation.requests.allowed-service-points.get
            $response = $this->makeRequest(
                'GET',
                '/circulation/requests/allowed-service-points?'
                . http_build_query(compact(empty($itemId) ? 'instanceId' : 'itemId', 'requesterId', 'operation'))
            );
            if (!$response->isSuccess()) {
                $this->warning('Unexpected service point lookup response: ' . $response->getBody());
                return null;
            }
        } catch (\Exception $e) {
            $this->warning('Exception during allowed service point lookup: ' . (string)$e);
            return null;
        }
        return json_decode($response->getBody(), true);
    }

    /**
     * MSUL PC-1405 Pass item_id to getAllowedServicePoints
     * Place Hold
     *
     * Attempts to place a hold or recall on a particular item and returns
     * an array with result details.
     *
     * @param array $holdDetails An array of item and patron data
     *
     * @return mixed An array of data on the request including
     * whether or not it was successful and a system message (if available)
     */
    public function placeHold($holdDetails)
    {
        if (
            !empty($holdDetails['requiredByTS'])
            && !is_int($holdDetails['requiredByTS'])
        ) {
            throw new ILSException('hold_date_invalid');
        }
        $requiredBy = !empty($holdDetails['requiredByTS'])
            ? gmdate('Y-m-d', $holdDetails['requiredByTS']) : null;

        $instance = $this->getInstanceByBibId($holdDetails['id']);
        $isTitleLevel = ($holdDetails['level'] ?? '') === 'title';
        if ($isTitleLevel) {
            $baseParams = [
                'instanceId' => $instance->id,
                'requestLevel' => 'Title',
            ];
        } else {
            // Note: early Lotus releases require instanceId and holdingsRecordId
            // to be set here as well, but the requirement was lifted in a hotfix
            // to allow backward compatibility. If you need compatibility with one
            // of those versions, you can add additional identifiers here, but
            // applying the latest hotfix is a better solution!
            $baseParams = ['itemId' => $holdDetails['item_id']];
        }
        // Account for an API spelling change introduced in mod-circulation v24:
        $fulfillmentKey = $this->getModuleMajorVersion('mod-circulation') >= 24
            ? 'fulfillmentPreference' : 'fulfilmentPreference';
        $fulfillmentValue = $holdDetails['requestGroupId'] ?? 'Hold Shelf';
        $fulfillmentLocationKey = match ($fulfillmentValue) {
            'Hold Shelf' => 'pickupServicePointId',
            'Delivery' => 'deliveryAddressTypeId',
        };
        $requestBody = $baseParams + [
            'requesterId' => $holdDetails['patron']['id'],
            'requestDate' => date('c'),
            $fulfillmentKey => $fulfillmentValue,
            'requestExpirationDate' => $requiredBy,
            $fulfillmentLocationKey => $holdDetails['pickUpLocation'],
        ];
        if (!empty($holdDetails['proxiedUser'])) {
            $requestBody['requesterId'] = $holdDetails['proxiedUser'];
            $requestBody['proxyUserId'] = $holdDetails['patron']['id'];
        }
        if (!empty($holdDetails['comment'])) {
            $requestBody['patronComments'] = $holdDetails['comment'];
        }
        // MSU - add item_id parameter
        $allowed = $this->getAllowedServicePoints(
            $instance->id,
            $holdDetails['patron']['id'],
            'create',
            $holdDetails['item_id']
        );
        $preferredRequestType = $this->getPreferredRequestType($holdDetails);
        foreach ($this->getRequestTypeList($preferredRequestType) as $requestType) {
            // Skip illegal request types, if we have validation data available:
            if (null !== $allowed) {
                if (
                    // Unsupported request type:
                    !isset($allowed[$requestType])
                    // Unsupported pickup location:
                    || !in_array($holdDetails['pickUpLocation'], array_column($allowed[$requestType] ?? [], 'id'))
                ) {
                    continue;
                }
            }
            $requestBody['requestType'] = $requestType;
            $result = $this->performHoldRequest($requestBody);
            if ($result['success']) {
                break;
            }
        }
        return $result ?? ['success' => false, 'status' => 'Unexpected failure'];
    }

    /**
     * MSUL PC-1405 Pass item_id to getAllowedServicePoints
     * Check if request is valid
     *
     * This is responsible for determining if an item is requestable
     *
     * @param string $id     The record id
     * @param array  $data   An array of item data
     * @param array  $patron An array of patron data
     *
     * @return array Two entries: 'valid' (boolean) plus 'status' (message to display to user)
     */
    public function checkRequestIsValid($id, $data, $patron)
    {
        // First check outstanding loans:
        $currentLoan = empty($data['item_id'])
            ? null
            : $this->getCurrentLoan($data['item_id']);
        if ($currentLoan && !$this->isHoldableByCurrentLoan($currentLoan)) {
            return [
                'valid' => false,
                'status' => 'hold_error_current_loan_patron_group',
            ];
        }

        // MSU - add item_id parameter
        $allowed = $this->getAllowedServicePoints(
            $this->getInstanceByBibId($id)->id,
            $patron['id'],
            'create',
            $data['item_id']
        );
        return [
            // If we got this far, it's valid if we can't obtain allowed service point
            // data, or if the allowed service point data is non-empty:
            'valid' => null === $allowed || !empty($allowed),
            'status' => 'request_place_text',
        ];
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
}
