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

use DateTime;
use DateTimeZone;

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
     * This method queries the ILS for holding information.
     * This extends the original by adding enumchron from $item->volume.
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
        $instance = $this->getInstanceByBibId($bibId);
        $query = [
            'query' => '(instanceId=="' . $instance->id
                . '" NOT discoverySuppress==true)'
        ];
        $items = [];
        foreach ($this->getPagedResults(
            'holdingsRecords',
            '/holdings-storage/holdings',
            $query
        ) as $holding) {
            $query = [
                'query' => 'holdingsRecordId=="' . $holding->id
                    . '" NOT discoverySuppress==true sortBy volume'
            ];
            $notesFormatter = function ($note) {
                return !($note->staffOnly ?? false)
                    && !empty($note->note) ? $note->note : '';
            };
            $textFormatter = function ($supplement) {
                $format = '%s %s';
                $supStat = $supplement->statement ?? '';
                $supNote = $supplement->note ?? '';
                $statement = trim(sprintf($format, $supStat, $supNote));
                return $statement ?? '';
            };
            $holdingNotes = array_filter(
                array_map($notesFormatter, $holding->notes ?? [])
            );
            $hasHoldingNotes = !empty(implode($holdingNotes));
            $holdingsStatements = array_map(
                $textFormatter,
                $holding->holdingsStatements ?? []
            );
            $holdingsSupplements = array_map(
                $textFormatter,
                $holding->holdingsStatementsForSupplements ?? []
            );
            $holdingsIndexes = array_map(
                $textFormatter,
                $holding->holdingsStatementsForIndexes ?? []
            );
            $holdingCallNumber = $holding->callNumber ?? '';
            $holdingCallNumberPrefix = $holding->callNumberPrefix ?? '';
            foreach ($this->getPagedResults(
                'items',
                '/item-storage/items',
                $query
            ) as $item) {
                $itemNotes = array_filter(
                    array_map($notesFormatter, $item->notes ?? [])
                );
                $locationId = $item->effectiveLocationId;
                $locationData = $this->getLocationData($locationId);
                $locationName = $locationData['name'];
                $locationCode = $locationData['code'];
                $callNumberData = $this->chooseCallNumber(
                    $holdingCallNumberPrefix,
                    $holdingCallNumber,
                    $item->itemLevelCallNumberPrefix ?? '',
                    $item->itemLevelCallNumber ?? ''
                );
                // concatenate enumeration fields if present
                $enum = implode(
                    ' ', array_filter(
                        [
                            $item->volume ?? null,
                            $item->enumeration ?? null,
                            $item->chronology ?? null
                        ]
                    )
                );
                $enum = str_ends_with($holdingCallNumber, $enum) ? '' : $enum;

                $items[] = $callNumberData + [
                    'id' => $bibId,
                    'item_id' => $item->id,
                    'holding_id' => $holding->id,
                    'number' => 0, # will be set afterwards
                    'enumchron' => $enum,
                    'barcode' => $item->barcode ?? '',
                    'status' => $item->status->name,
                    'availability' => $item->status->name == 'Available',
                    'is_holdable' => $this->isHoldable($locationName),
                    'holdings_notes'=> $hasHoldingNotes ? $holdingNotes : null,
                    'item_notes' => !empty(implode($itemNotes)) ? $itemNotes : null,
                    'issues' => $holdingsStatements,
                    'supplements' => $holdingsSupplements,
                    'indexes' => $holdingsIndexes,
                    'location' => $locationName,
                    'location_code' => $locationCode,
                    'reserve' => 'TODO',
                    'addLink' => true,
                    'electronic_access' => $item->electronicAccess
                ];
            }
        }

        // Check if there are any bound-with items that need to be added
        // and then merge them into the list if we don't already have that item
        $item_ids = array_column($items, 'item_id');
        foreach ($this->getBoundwithHoldings($bibId, $instance->id) as $bound_item) {
            if (!in_array($bound_item['item_id'], $item_ids)) {
                array_push($items, $bound_item);
            }
        }

        // Sort by enumchron (volume) and set the number (copy) field
        uasort($items, function($item1, $item2) {
            return $item2['location'] <=> $item1['location'] ?: # reverse sort
                   version_compare($item1['enumchron'], $item2['enumchron']) ?:
                   $item1['id'] <=> $item2['id'];
        });
        $prev_enumchron = 'INVALID';
        $prev_location = 'INVALID';
        $number = 1;
        foreach ($items as &$item) {
            if ($item['enumchron'] == $prev_enumchron && $item['location'] == $prev_location){
                $number += 1;
            } else {
                $number = 1;
            }
            $item['number'] = $number;
            $prev_enumchron = $item['enumchron'];
            $prev_location = $item['location'];
        }

        return $items;
    }

    /**
     * Get the bound-with items associated with the instance ID
     * @param string $bibId         Bib-level id
     * @param string $instanceId    Instance-level id
     *
     * @return array An array of associative holding arrays
     */
    public function getBoundwithHoldings($bibId, $instanceId)
    {
        $items = [];

        $query = [
            'query' => 'instanceId==' . $instanceId
        ];
        foreach ($this->getPagedResults(
            'holdingsRecords',
            '/holdings-storage/holdings',
            $query
        ) as $bound_holding) {
            $query = [
                'query' => 'holdingsRecordId=="' . $bound_holding->id . '"'
            ];
            foreach ($this->getPagedResults(
                'boundWithParts',
                '/inventory-storage/bound-with-parts',
                $query
            ) as $bound) {
                $response = $this->makeRequest(
                    'GET',
                    '/item-storage/items/' . $bound->itemId
                );
                $bound_item = json_decode($response->getBody());

                // Formatter helper functions
                $notesFormatter = function ($note) {
                    return !($note->staffOnly ?? false)
                        && !empty($note->note) ? $note->note : '';
                };
                $textFormatter = function ($supplement) {
                    $format = '%s %s';
                    $supStat = $supplement->statement ?? '';
                    $supNote = $supplement->note ?? '';
                    $statement = trim(sprintf($format, $supStat, $supNote));
                    return $statement ?? '';
                };

                $holdingNotes = array_filter(
                    array_map($notesFormatter, $bound_holding->notes ?? [])
                );
                $hasHoldingNotes = !count($holdingNotes) > 0;
                $holdingsStatements = array_map(
                    $textFormatter,
                    $bound_holding->holdingsStatements ?? []
                );
                $holdingsSupplements = array_map(
                    $textFormatter,
                    $bound_holding->holdingsStatementsForSupplements ?? []
                );
                $holdingsIndexes = array_map(
                    $textFormatter,
                    $bound_holding->holdingsStatementsForIndexes ?? []
                );
                $locationId = $bound_item->effectiveLocationId;
                $locationData = $this->getLocationData($locationId);
                $locationName = $locationData['name'];
                $locationCode = $locationData['code'];
                $holdingCallNumber = $bound_holding->callNumber ?? '';
                $holdingCallNumberPrefix = $bound_holding->callNumberPrefix ?? '';
                $callNumberData = $this->chooseCallNumber(
                    $holdingCallNumberPrefix,
                    $holdingCallNumber,
                    $bound_item->itemLevelCallNumberPrefix ?? '',
                    $bound_item->itemLevelCallNumber ?? ''
                );
                // concatenate enumeration fields if present
                $enum = implode(
                    ' ', array_filter(
                        [
                            $bound_item->volume ?? null,
                            $bound_item->enumeration ?? null,
                            $bound_item->chronology ?? null
                        ]
                    )
                );
                $enum = str_ends_with($holdingCallNumber, $enum) ? '' : $enum;

                $items[] = $callNumberData + [
                    'id' => $bibId,
                    'item_id' => $bound->itemId,
                    'holding_id' => $bound_holding->id,
                    'number' => 0, # will be set afterwards
                    'enumchron' => $enum,
                    'barcode' => $bound_item->barcode ?? '',
                    'status' => $bound_item->status->name,
                    'availability' => $bound_item->status->name == 'Available',
                    'is_holdable' => $this->isHoldable($locationName),
                    'holdings_notes'=> $hasHoldingNotes ? $holdingNotes : null,
                    'item_notes' => !empty($itemNotes) ? $itemNotes : null,
                    'issues' => $holdingsStatements,
                    'supplements' => $holdingsSupplements,
                    'indexes' => $holdingsIndexes,
                    'location' => $locationName,
                    'location_code' => $locationCode,
                    'reserve' => 'TODO',
                    'addLink' => true
                ];
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
        // MSUL -- overridden to add sortBy
        $query = ['query' => 'userId==' . $patron['id'] . ' and status.name==Open sortBy dueDate/sort.ascending'];
        $transactions = [];
        foreach ($this->getPagedResults(
            'loans',
            '/circulation/loans',
            $query
        ) as $trans) {
            $date = new DateTime($trans->dueDate, new DateTimeZone('UTC'));
            $localTimezone = (new DateTime)->getTimezone();
            $date->setTimezone($localTimezone);

            $dueStatus = false;
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
                'volume' => $trans->item->volume,
                'callNumber' => $trans->item->callNumber
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
        $idType = $this->getBibIdType();
        $query = [
            'query' => 'copiedItem.instanceDiscoverySuppress==false'
        ];

        // Results can be paginated, so let's loop until we've gotten everything:
        foreach ($this->getPagedResults(
            'reserves',
            '/coursereserves/reserves',
            $query
        ) as $item) {
            if ($idType == 'hrid') {
                $bibId = $item->copiedItem->instanceHrid ?? null;
            } else {
                $bibId = $item->copiedItem->instanceId ?? null;
            }
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

    protected function getElectronicAccessLinks($itemId)
    {
        try {
            $response = $this->makeRequest(
                'GET',
                '/item-storage/items/' . $itemId
            );
            $item = json_decode($response->getBody());
            return $item->electronicAccess;
        } catch (\VuFind\Exception\RecordMissing $e) {
            return [];
        }
    }

    /**
     * Check item location against list of configured locations
     * where holds should be offered
     *
     * @param string $locationName locationName from getHolding
     *
     * @return bool
     */
    protected function isHoldable($locationName)
    {
        $mode = $this->config['Holds']['excludeHoldLocationsCompareMode'] ?? 'exact';
        $excludeLocs = (array)($this->config['Holds']['excludeHoldLocations'] ?? []);

        // Exclude checking by regex match
        if (trim(strtolower($mode)) == "regex") {
            foreach ($excludeLocs as $pattern) {
                $match = @preg_match($pattern, $locationName);
                // Invalid regex, skip this pattern
                if ($match === false) {
                    $this->logWarning(
                        'Invalid regex found in excludeHoldLocations: ' .
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
        return !in_array($locationName, $excludeLocs);
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
        foreach ($this->getPagedResults(
            'servicepoints',
            '/service-points',
            $query
        ) as $servicepoint) {
            if ($this->isPickupable($servicepoint->discoveryDisplayName)) {
                $locations[] = [
                    'locationID' => $servicepoint->id,
                    'locationDisplay' => $servicepoint->discoveryDisplayName
                ];
            }
        }
        return $locations;
    }

    /*
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

    /*
     * https://github.com/vufind-org/vufind/pull/2739 has been merged to fix the issue we
     * were having. Once it is included in a release, we can remove this extended function.
     */
    public function placeHold($holdDetails)
    {
        $default_request = $this->config['Holds']['default_request'] ?? 'Hold';
        if (
            !empty($holdDetails['requiredByTS'])
            && !is_int($holdDetails['requiredByTS'])
        ) {
            throw new ILSException('hold_date_invalid');
        }
        $requiredBy = !empty($holdDetails['requiredByTS'])
            ? gmdate('Y-m-d', $holdDetails['requiredByTS']) : null;

        $isTitleLevel = ($holdDetails['level'] ?? '') === 'title';
        if ($isTitleLevel) {
            $instance = $this->getInstanceByBibId($holdDetails['id']);
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
        $requestBody = $baseParams + [
            'requestType' => $holdDetails['status'] == 'Available'
                ? 'Page' : $default_request,
            'requesterId' => $holdDetails['patron']['id'],
            'requestDate' => date('c'),
            'fulfilmentPreference' => 'Hold Shelf',
            'requestExpirationDate' => $requiredBy,
            'pickupServicePointId' => $holdDetails['pickUpLocation'],
        ];
        if (!empty($holdDetails['proxiedUser'])) {
            $requestBody['requesterId'] = $holdDetails['proxiedUser'];
            $requestBody['proxyUserId'] = $holdDetails['patron']['id'];
        }
        if (!empty($holdDetails['comment'])) {
            $requestBody['patronComments'] = $holdDetails['comment'];
        }
        $response = $this->makeRequest(
            'POST',
            '/circulation/requests',
            json_encode($requestBody),
            [],
            true
        );
        if ($response->isSuccess()) {
            $json = json_decode($response->getBody());
            $result = [
                'success' => true,
                'status' => $json->status,
            ];
        } else {
            try {
                $json = json_decode($response->getBody());
                $result = [
                    'success' => false,
                    'status' => $json->errors[0]->message,
                ];
            } catch (Exception $e) {
                $this->throwAsIlsException($e, $response->getBody());
            }
        }
        return $result;
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
        // MSUL customization to add fields (including multi backend prefix) and sorting to query
        $query = [
            'query' => '(requesterId == "' . $patron['id'] . '"  ' .
            'and status == Open*) sortBy requestDate/sort.ascending title/sort.ascending'
        ];
        $holds = [];
        $allowCancelingAvailableRequests
            = $this->config['Holds']['allowCancelingAvailableRequests'] ?? true;
        foreach ($this->getPagedResults(
            'requests',
            '/request-storage/requests',
            $query
        ) as $hold) {
            $requestDate = date_create($hold->requestDate);
            // Set expire date if it was included in the response
            $expireDate = isset($hold->requestExpirationDate)
                ? date_create($hold->requestExpirationDate) : null;
            $available = (string)$hold->status === 'Open - Awaiting pickup';
            $servicePoint = isset($hold->pickupServicePointId)
                ? $this->getPickupLocation($hold->pickupServicePointId) : null;
            $location = isset($servicePoint) && count($servicePoint) == 1
                ? $servicePoint[0]['locationDisplay'] : "";
            $request_id = $this->getBibId(null, null, $hold->itemId);
            $updateDetails = (!$available || $allowCancelingAvailableRequests)
                ? (string)$request_id : '';
            $rec = [
                'type' => $hold->requestType,
                'create' => date_format($requestDate, "j M Y"),
                'expire' => isset($expireDate)
                    ? date_format($expireDate, "j M Y") : "",
                'id' => 'folio.' . $request_id,
                'available' => $available,
                'processed' => $hold->status !== 'Open - Not yet filled',
                'location' => $location,
                'updateDetails' => $updateDetails,
                'item_id' => $hold->itemId,
                'reqnum' => $hold->id,
                // Title moved from item to instance in Lotus release:
                'title' => $hold->instance->title ?? $hold->item->title ?? '',
                'status' => $hold->status,
                // last_pickup_date,
            ];
            if ($hold->status === 'Open - In transit') {
                $rec['in_transit'] = true;
            }
            $holds[] = $rec;
        }
        return $holds;
    }

    public function getPickupLocation($locationId)
    {
        $query = ['query' => 'id == "' . $locationId . '"  '];
        $locations = [];
        foreach ($this->getPagedResults(
            'servicepoints',
            '/service-points',
            $query
        ) as $servicepoint) {
            $locations[] = [
                'locationID' => $servicepoint->id,
                'locationDisplay' => $servicepoint->discoveryDisplayName
            ];
        }
        return $locations;
    }

    public function getInstanceByBibId($bibId)
    {
        // MSUL override to make publicly available to reserve index command

        // Figure out which ID type to use in the CQL query; if the user configured
        // instance IDs, use the 'id' field, otherwise pass the setting through
        // directly:
        $idType = $this->getBibIdType();
        $idField = $idType === 'instance' ? 'id' : $idType;

        $query = [
            'query' => '(' . $idField . '=="' . $this->escapeCql($bibId) . '")'
        ];
        $response = $this->makeRequest('GET', '/instance-storage/instances', $query);
        $instances = json_decode($response->getBody());
        if (count($instances->instances) == 0) {
            throw new ILSException("Item Not Found");
        }
        return $instances->instances[0];
    }
}
