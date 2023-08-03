<?php

/**
 * FOLIO REST API driver
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2022.
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
 * @author   Damien Guillaume <damieng@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */

namespace Catalog\ILS\Driver;

use VuFind\Exception\ILS as ILSException;

/**
 * FOLIO REST API driver
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Damien Guillaume <damieng@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class Folio extends \VuFind\ILS\Driver\Folio
{
    /**
     * Support method for getHolding(): extract details from the holding record that
     * will be needed by formatHoldingItem() below.
     *
     * See https://github.com/vufind-org/vufind/pull/2983
     *
     * @param object $holding FOLIO holding record (decoded from JSON)
     *
     * @return array
     */
    protected function getHoldingDetailsForItem($holding): array
    {
        $textFormatter = function ($supplement) {
            $format = '%s %s';
            $supStat = $supplement->statement ?? '';
            $supNote = $supplement->note ?? '';
            $statement = trim(sprintf($format, $supStat, $supNote));
            return $statement;
        };
        $id = $holding->id;
        $holdingNotes = array_filter(
            array_map([$this, 'formatNote'], $holding->notes ?? [])
        );
        $hasHoldingNotes = !empty(implode($holdingNotes));
        $holdingsStatements = array_filter(array_map(
            $textFormatter,
            $holding->holdingsStatements ?? []
        ));
        $holdingsSupplements = array_filter(array_map(
            $textFormatter,
            $holding->holdingsStatementsForSupplements ?? []
        ));
        $holdingsIndexes = array_filter(array_map(
            $textFormatter,
            $holding->holdingsStatementsForIndexes ?? []
        ));
        $holdingCallNumber = $holding->callNumber ?? '';
        $holdingCallNumberPrefix = $holding->callNumberPrefix ?? '';
        return compact(
            'id',
            'holdingNotes',
            'hasHoldingNotes',
            'holdingsStatements',
            'holdingsSupplements',
            'holdingsIndexes',
            'holdingCallNumber',
            'holdingCallNumberPrefix'
        );
    }

    /**
     * Support method for getHolding() -- given a few key details, format an item
     * for inclusion in the return value.
     *
     * @param string $bibId          Current bibliographic ID
     * @param array  $holdingDetails Holding details produced by
     * getHoldingDetailsForItem()
     * @param object $item           FOLIO item record (decoded from JSON)
     * @param int    $number         The current item number (position within
     * current holdings record)
     * @param string $dueDateValue   The due date to display to the user
     *
     * @return array
     */
    protected function formatHoldingItem(
        string $bibId,
        array $holdingDetails,
        $item,
        $number,
        string $dueDateValue
    ): array {
        $itemNotes = array_filter(
            array_map([$this, 'formatNote'], $item->notes ?? [])
        );
        $locationId = $item->effectiveLocationId;
        $locationData = $this->getLocationData($locationId);
        $locationName = $locationData['name'];
        $locationCode = $locationData['code'];
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
        $enum = str_ends_with($holdingDetails['holdingCallNumber'], $enum) ? '' : $enum;
        $callNumberData = $this->chooseCallNumber(
            $holdingDetails['holdingCallNumberPrefix'],
            $holdingDetails['holdingCallNumber'],
            $item->effectiveCallNumberComponents->prefix
                ?? $item->itemLevelCallNumberPrefix ?? '',
            $item->effectiveCallNumberComponents->callNumber
                ?? $item->itemLevelCallNumber ?? ''
        );

        return $callNumberData + [
            'id' => $bibId,
            'item_id' => $item->id,
            'holding_id' => $holdingDetails['id'],
            'number' => $number,
            'enumchron' => $enum,
            'barcode' => $item->barcode ?? '',
            'status' => $item->status->name,
            'duedate' => $dueDateValue,
            'availability' => $item->status->name == 'Available',
            'is_holdable' => $this->isHoldable($locationName),
            'holdings_notes' => $holdingDetails['hasHoldingNotes']
                ? $holdingDetails['holdingNotes'] : null,
            'item_notes' => !empty(implode($itemNotes)) ? $itemNotes : null,
            'issues' => $holdingDetails['holdingsStatements'],
            'supplements' => $holdingDetails['holdingsSupplements'],
            'indexes' => $holdingDetails['holdingsIndexes'],
            'location' => $locationName,
            'location_code' => $locationCode,
            'reserve' => 'TODO',
            'addLink' => true,
            'electronic_access' => $item->electronicAccess,
        ];
    }

    /**
     * This method queries the ILS for holding information.
     *
     * @param string $bibId   Bib-level id
     * @param array  $patron  Patron login information from $this->patronLogin
     * @param array  $options Extra options (not currently used)
     *
     * @return array An array of associative holding arrays
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getHolding($bibId, array $patron = null, array $options = [])
    {
        $showDueDate = $this->config['Availability']['showDueDate'] ?? true;
        $showTime = $this->config['Availability']['showTime'] ?? false;
        $maxNumDueDateItems = $this->config['Availability']['maxNumberItems'] ?? 5;
        $dueDateItemCount = 0;

        $instance = $this->getInstanceByBibId($bibId);
        $query = [
            'query' => '(instanceId=="' . $instance->id
                . '" NOT discoverySuppress==true)',
        ];
        $items = [];
        $folioItemSort = $this->config['Holdings']['folio_sort'] ?? '';
        $vufindItemSort = $this->config['Holdings']['vufind_sort'] ?? '';
        foreach (
            $this->getPagedResults(
                'holdingsRecords',
                '/holdings-storage/holdings',
                $query
            ) as $holding
        ) {
            $rawQuery = '(holdingsRecordId=="' . $holding->id
                . '" NOT discoverySuppress==true)';
            if (!empty($folioItemSort)) {
                $rawQuery .= ' sortby ' . $folioItemSort;
            }
            $query = ['query' => $rawQuery];
            $holdingDetails = $this->getHoldingDetailsForItem($holding);
            $nextBatch = [];
            $sortNeeded = false;
            foreach (
                $this->getPagedResults(
                    'items',
                    '/item-storage/items',
                    $query
                ) as $item
            ) {
                $number = $item->copyNumber ?? null;
                $dueDateValue = '';
                if (
                    $item->status->name == 'Checked out'
                    && $showDueDate
                    && $dueDateItemCount < $maxNumDueDateItems
                ) {
                    $dueDateValue = $this->getDueDate($item->id, $showTime);
                    $dueDateItemCount++;
                }
                $nextItem = $this->formatHoldingItem(
                    $bibId,
                    $holdingDetails,
                    $item,
                    $number,
                    $dueDateValue
                );
                if (!empty($vufindItemSort) && !empty($nextItem[$vufindItemSort])) {
                    $sortNeeded = true;
                }
                $nextBatch[] = $nextItem;
            }
            $items = array_merge(
                $items,
                $sortNeeded
                    ? $this->sortHoldings($nextBatch, $vufindItemSort) : $nextBatch
            );
        }

        // Check if there are any bound-with items that need to be added
        // and then merge them into the list if we don't already have that item
        $item_ids = array_column($items, 'item_id');
        foreach ($this->getBoundwithHoldings($bibId, $instance->id) as $bound_item) {
            if (!in_array($bound_item['item_id'], $item_ids)) {
                array_push($items, $bound_item);
            }
        }

        // Sort by location, enumchron (volume) and copy number
        uasort($items, function ($item1, $item2) {
            return $item2['location'] <=> $item1['location'] ?: // reverse sort
                   version_compare($item1['enumchron'], $item2['enumchron']) ?:
                   $item1['number'] <=> $item2['number'] ?:
                   $item1['id'] <=> $item2['id'];
        });

        return $items;
    }

    /**
     * Get the bound-with items associated with the instance ID
     *
     * @param string $bibId      Bib-level id
     * @param string $instanceId Instance-level id
     *
     * @return array An array of associative holding arrays
     */
    public function getBoundwithHoldings($bibId, $instanceId)
    {
        $showDueDate = $this->config['Availability']['showDueDate'] ?? true;
        $showTime = $this->config['Availability']['showTime'] ?? false;
        $maxNumDueDateItems = $this->config['Availability']['maxNumberItems'] ?? 5;
        $dueDateItemCount = 0;

        $items = [];

        $query = [
            'query' => 'instanceId==' . $instanceId,
        ];
        foreach (
            $this->getPagedResults(
                'holdingsRecords',
                '/holdings-storage/holdings',
                $query
            ) as $bound_holding
        ) {
            $query = [
                'query' => 'holdingsRecordId=="' . $bound_holding->id . '"',
            ];
            $holdingDetails = $this->getHoldingDetailsForItem($bound_holding);
            foreach (
                $this->getPagedResults(
                    'boundWithParts',
                    '/inventory-storage/bound-with-parts',
                    $query
                ) as $bound
            ) {
                $response = $this->makeRequest(
                    'GET',
                    '/item-storage/items/' . $bound->itemId
                );
                $bound_item = json_decode($response->getBody());
                $number = $bound_item->copyNumber ?? null;
                $dueDateValue = '';
                if (
                    $bound_item->status->name == 'Checked out'
                    && $showDueDate
                    && $dueDateItemCount < $maxNumDueDateItems
                ) {
                    $dueDateValue = $this->getDueDate($bound_item->id, $showTime);
                    $dueDateItemCount++;
                }
                $items[] = $this->formatHoldingItem(
                    $bibId,
                    $holdingDetails,
                    $bound_item,
                    $number,
                    $dueDateValue
                );
            }
        }
        return $items;
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
     *
     * @return array Transactions associative arrays
     */
    public function getMyTransactions($patron)
    {
        // MSUL -- overridden to add sortBy and add fields to response
        $query = ['query' => 'userId==' . $patron['id'] . ' and status.name==Open sortBy dueDate/sort.ascending'];
        $transactions = [];
        foreach (
            $this->getPagedResults(
                'loans',
                '/circulation/loans',
                $query
            ) as $trans
        ) {
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
                'id' => "folio." . $this->getBibId($trans->item->instanceId),
                'item_id' => $trans->item->id,
                'barcode' => $trans->item->barcode,
                'renew' => $trans->renewalCount ?? 0,
                'renewable' => true,
                'title' => $trans->item->title,
                'borrowingLocation' => $trans->item->location->name,
                'volume' => $trans->item->volume ?? null,
                'callNumber' => $trans->item->callNumber,
            ];
        }
        return $transactions;
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
        $query = [
            'query' => 'copiedItem.instanceDiscoverySuppress==false',
        ];

        // Results can be paginated, so let's loop until we've gotten everything:
        foreach (
            $this->getPagedResults(
                'reserves',
                '/coursereserves/reserves',
                $query
            ) as $item
        ) {
            $idProperty = $this->getBibIdType() === 'hrid'
                ? 'instanceHrid' : 'instanceId';
            $bibId = $item->copiedItem->$idProperty ?? null;
            if ($bibId !== null) {
                $bibId = "folio." . $bibId;
            }

            // Get the electronic access links from the item record if possible
            // electronicAccess will be an array with keys: uri, linkText, publicNote, relationshipId
            $itemId = $item->itemId ?? null;
            $electronicAccess = null;
            $urlPattern = '/https?:\/\/catalog\.lib\.msu\.edu\/Record\/([.a-zA-Z0-9]+)/i';
            if ($itemId !== null) {
                $links = $this->getElectronicAccessLinks($itemId);
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
     * @return associative array of the link data
     */
    protected function getElectronicAccessLinks($itemId)
    {
        try {
            $response = $this->makeRequest(
                'GET',
                '/item-storage/items/' . $itemId
            );
            $item = json_decode($response->getBody());
            return $item->electronicAccess;
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
     * in the context of placing or editing a hold.  When placing a hold, it contains
     * most of the same values passed to placeHold, minus the patron data.  When
     * editing a hold it contains all the hold information returned by getMyHolds.
     * May be used to limit the pickup options or may be ignored.  The driver must
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
        $query = ['query' => 'pickupLocation=true'];
        $locations = [];
        foreach (
            $this->getPagedResults(
                'servicepoints',
                '/service-points',
                $query
            ) as $servicepoint
        ) {
            if ($this->isPickupable($servicepoint->discoveryDisplayName)) {
                $locations[] = [
                    'locationID' => $servicepoint->id,
                    'locationDisplay' => $servicepoint->discoveryDisplayName,
                ];
            }
        }
        return $locations;
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
        if (trim(strtolower($mode)) == "regex") {
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
                "Y-m-d H:i",
                $hold->requestDate
            );
            // Set expire date if it was included in the response
            $expireDate = isset($hold->requestExpirationDate)
                ? $this->dateConverter->convertToDisplayDate(
                    "Y-m-d H:i",
                    $hold->requestExpirationDate
                )
                : null;
            // Set lastPickup Date if provided, format to j M Y
            $lastPickup = isset($hold->holdShelfExpirationDate)
                ? $this->dateConverter->convertToDisplayDate(
                    "Y-m-d H:i",
                    $hold->holdShelfExpirationDate
                )
                : null;
            $available = in_array(
                $hold->status,
                $this->config['Holds']['available']
                ?? $this->defaultAvailabilityStatuses
            );
            $servicePoint = isset($hold->pickupServicePointId)
                ? $this->getPickupLocation($hold->pickupServicePointId) : null;
            $location = isset($servicePoint) && count($servicePoint) == 1
                ? $servicePoint[0]['locationDisplay'] : "";
            $request_id = $this->getBibId(null, null, $hold->itemId);
            $updateDetails = (!$available || $allowCancelingAvailableRequests)
                ? (string)$request_id : '';
            $currentHold = [
                'type' => $hold->requestType,
                'create' => $requestDate,
                'expire' => $expireDate ?? "",
                // MSU customization: id changed for multi-backend
                'id' => 'folio.' . $request_id,
                'item_id' => $hold->itemId,
                'reqnum' => $hold->id,
                // Title moved from item to instance in Lotus release:
                'title' => $hold->instance->title ?? $hold->item->title ?? '',
                'available' => $available,
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
     * Get the instance record by the Sierra bib number
     *
     * @param string $bibId Bib number
     *
     * @return array of instance data
     */
    public function getInstanceByBibId($bibId)
    {
        // MSUL override to make publicly available to reserve index command

        // Figure out which ID type to use in the CQL query; if the user configured
        // instance IDs, use the 'id' field, otherwise pass the setting through
        // directly:
        $idType = $this->getBibIdType();
        $idField = $idType === 'instance' ? 'id' : $idType;

        $query = [
            'query' => '(' . $idField . '=="' . $this->escapeCql($bibId) . '")',
        ];
        $response = $this->makeRequest('GET', '/instance-storage/instances', $query);
        $instances = json_decode($response->getBody());
        if (count($instances->instances) == 0) {
            throw new ILSException("Item Not Found");
        }
        return $instances->instances[0];
    }

    /**
     * MSUL Customization that was merged in as a PR and will be in v9.1
     * Helper function to retrieve paged results from FOLIO API
     *
     * @param string $responseKey Key containing values to collect in response
     * @param string $interface   FOLIO api interface to call
     * @param array  $query       CQL query
     * @param int    $limit       How many results to retrieve from FOLIO per call
     *
     * @return array
     */
    protected function getPagedResults($responseKey, $interface, $query = [], $limit = 1000)
    {
        $offset = 0;

        do {
            $combinedQuery = array_merge($query, compact('offset', 'limit'));
            $response = $this->makeRequest(
                'GET',
                $interface,
                $combinedQuery
            );
            $json = json_decode($response->getBody());
            if (!$response->isSuccess() || !$json) {
                $msg = $json->errors[0]->message ?? json_last_error_msg();
                throw new ILSException("Error: '$msg' fetching '$responseKey'");
            }
            $totalEstimate = $json->totalRecords ?? 0;
            foreach ($json->$responseKey ?? [] as $item) {
                yield $item ?? '';
            }
            $offset += $limit;

            // Continue until the current offset is greater than the totalRecords value returned
            // from the API (which could be an estimate if more than 1000 results are returned).
        } while ($offset <= $totalEstimate);
    }
}
