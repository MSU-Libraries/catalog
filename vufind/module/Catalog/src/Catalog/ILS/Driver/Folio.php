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
 * @author   Damien Guillaume <damieng@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */

namespace Catalog\ILS\Driver;

use ArrayIterator;
use Catalog\Utils\RegexLookup as Regex;
use Laminas\Http\Header\HeaderInterface;
use Laminas\Http\Response; // MSU - Can remove after we upgrade to VF 10.1
use Catalog\Config\Feature\SecretTrait; // MSU  - Can remove after we upgrade to VF 10.1.2
use VuFind\Exception\ILS as ILSException;

use function count;
use function in_array;
use function is_object;
use function is_string;

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
    use SecretTrait; // CAN REMOVE AFTER WE UPDATE TO VF 10.1.2

    /**
     * Authentication token expiration time
     * CAN REMOVE AFTER WE UPDATE TO VF 10.1.2
     *
     * @var string
     */
    protected $tokenExpiration = null;

    /**
     *  -- CAN REMOVE ONCE WE UPGRADE TO VF 10.1
     * Support method for makeRequest to process an unexpected status code. Can return true to trigger
     * a retry of the API call or false to throw an exception.
     *
     * @param Response $response      HTTP response
     * @param int      $attemptNumber Counter to keep track of attempts (starts at 1 for the first attempt)
     *
     * @return bool
     */
    protected function shouldRetryAfterUnexpectedStatusCode(Response $response, int $attemptNumber): bool
    {
        // If the unexpected status is 401, and the token renews successfully, and we have not yet
        // retried, we should try again:
        if ($response->getStatusCode() === 401 && $attemptNumber < 2) {
            $this->debug('Retrying request after token expired...');
            return true;
        }
        return false;
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
     * Support method for getHolding() -- given a loan type ID return the string name for it
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
     * Support method for getHolding() -- given a few key details, format an item
     * for inclusion in the return value.
     *
     * @param string $bibId            Current bibliographic ID
     * @param array  $holdingDetails   Holding details produced by
     *                                 getHoldingDetailsForItem()
     * @param object $item             FOLIO item record (decoded from JSON)
     * @param int    $number           The current item number (position within
     *                                 current holdings record)
     * @param string $dueDateValue     The due date to display to the user
     * @param array  $boundWithRecords Any bib records this holding is bound with
     * @param string $tempLoanType     The temporary loan type for the item
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
        string $tempLoanType = null
    ): array {
        $itemNotes = array_filter(
            array_map([$this, 'formatNote'], $item->notes ?? [])
        );
        $locationId = $item->effectiveLocation->id;
        $locationData = $this->getLocationData($locationId);
        $locationName = $locationData['name'];
        $locationCode = $locationData['code'];
        $locationIsActive = $locationData['isActive'];
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

        // MSU START
        // PC-835: Items with loan type "Non Circulating" should show as "Lib Use Only" after they're checked in
        if (
            ($item->permanentLoanType->id ?? '') == 'adac93ac-951f-4f42-ab32-79f4faeabb50' &&
            $item->status->name == 'Available' &&
            !Regex::ONLINE($locationName)
        ) {
            $item->status->name = 'Restricted';
        }
        // MSU END

        return $callNumberData + [
            'id' => $bibId,
            'item_id' => $item->id,
            'holdings_id' => $holdingDetails['id'],
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
            'issues' => $holdingDetails['holdingsStatements'], // MSU
            'supplements' => $holdingDetails['holdingsSupplements'],
            'indexes' => $holdingDetails['holdingsIndexes'],
            'location' => $locationName,
            'location_code' => $locationCode,
            'folio_location_is_active' => $locationIsActive,
            'reserve' => 'TODO',
            'addLink' => true,
            'bound_with_records' => $boundWithRecords,
            'electronic_access' => $item->electronicAccess, // MSU
            'temporary_loan_type' => $tempLoanType, // MSU
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
            $rawQuery = '(holdingsRecordId=="' . $holding->id . '")';
            if (!empty($folioItemSort)) {
                $rawQuery .= ' sortby ' . $folioItemSort;
            }
            $query = ['query' => $rawQuery];
            $holdingDetails = $this->getHoldingDetailsForItem($holding);
            $nextBatch = [];
            $sortNeeded = false;
            $number = 0;
            foreach (
                $this->getPagedResults(
                    'items',
                    '/inventory/items-by-holdings-id',
                    $query
                ) as $item
            ) {
                if ($item->discoverySuppress ?? false) {
                    continue;
                }
                $number = $item->copyNumber ?? null; // MSU
                $dueDateValue = '';
                $boundWithRecords = null;
                if (
                    $item->status->name == 'Checked out'
                    && $showDueDate
                    && $dueDateItemCount < $maxNumDueDateItems
                ) {
                    $dueDateValue = $this->getDueDate($item->id, $showTime);
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
                    $number,
                    $dueDateValue,
                    $boundWithRecords ?? [],
                    $tempLoanType // MSU
                );
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
                'id' => $this->getBibId($trans->item->instanceId),
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
     * @return array associative array of the link data
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
        // PC-864 Sort the locations, if configured to do so
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
            $available = in_array(
                $hold->status,
                $this->config['Holds']['available']
                ?? $this->defaultAvailabilityStatuses
            );
            $servicePoint = isset($hold->pickupServicePointId)
                ? $this->getPickupLocation($hold->pickupServicePointId) : null;
            $location = isset($servicePoint) && count($servicePoint) == 1
                ? $servicePoint[0]['locationDisplay'] : '';
            $request_id = $this->getBibId(null, null, $hold->itemId);
            $updateDetails = (!$available || $allowCancelingAvailableRequests)
                ? (string)$request_id : '';
            $currentHold = [
                'type' => $hold->requestType,
                'create' => $requestDate,
                'expire' => $expireDate ?? '',
                'id' => $request_id,
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
            throw new ILSException('Item Not Found');
        }
        return $instances->instances[0];
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
        $customProperties = $licenses->records[0]?->linkedLicenses[0]?->remoteId_object?->customProperties;

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
        try {
            $response = $client->send();
        } catch (\Exception $e) {
            $this->logError('Unexpected ' . $e::class . ': ' . (string)$e);
            throw new ILSException('Error during send operation.');
        }
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
                $this->renewTenantToken(); // MSU Can likely remove after updating to 10.1
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
     * MSUL -- CAN REMOVE AFTER WE UPGRADE TO VF 10.1.2
     * Get a total count of records from a FOLIO endpoint.
     *
     * @param string $interface FOLIO api interface to call
     * @param array  $query     Extra GET parameters (e.g. ['query' => 'your cql here'])
     *
     * @return int
     */
    protected function getResultCount(string $interface, array $query = []): int
    {
        $combinedQuery = array_merge($query, ['limit' => 0]);
        $response = $this->makeRequest(
            'GET',
            $interface,
            $combinedQuery
        );
        $json = json_decode($response->getBody());
        return $json->totalRecords ?? 0;
    }

    /**
     * MSUL -- CAN REMOVE AFTER WE UPGRADE TO VF 10.1.2
     * Helper function to retrieve a single page of results from FOLIO API
     *
     * @param string $interface FOLIO api interface to call
     * @param array  $query     Extra GET parameters (e.g. ['query' => 'your cql here'])
     * @param int    $offset    Starting record index
     * @param int    $limit     Max number of records to retrieve
     *
     * @return array
     */
    protected function getResultPage($interface, $query = [], $offset = 0, $limit = 1000)
    {
        $combinedQuery = array_merge($query, compact('offset', 'limit'));
        $response = $this->makeRequest(
            'GET',
            $interface,
            $combinedQuery
        );
        $json = json_decode($response->getBody());
        if (!$response->isSuccess() || !$json) {
            $msg = $json->errors[0]->message ?? json_last_error_msg();
            throw new ILSException("Error: '$msg' fetching from '$interface'");
        }
        return $json;
    }

    /**
     * MSUL -- CAN REMOVE AFTER WE UPGRADE TO VF 10.1.2
     * Helper function to retrieve paged results from FOLIO API
     *
     * @param string $responseKey Key containing values to collect in response
     * @param string $interface   FOLIO api interface to call
     * @param array  $query       Extra GET parameters (e.g. ['query' => 'your cql here'])
     * @param int    $limit       How many results to retrieve from FOLIO per call
     *
     * @return array
     */
    protected function getPagedResults($responseKey, $interface, $query = [], $limit = 1000)
    {
        $offset = 0;

        do {
            $json = $this->getResultPage($interface, $query, $offset, $limit);
            $totalEstimate = $json->totalRecords ?? 0;
            foreach ($json->$responseKey ?? [] as $item) {
                yield $item ?? '';
            }
            $offset += $limit;

            // Continue until the current offset is greater than the totalRecords value returned
            // from the API (which could be an estimate if more than 1000 results are returned).
        } while ($offset <= $totalEstimate);
    }

    /**
     * MSUL -- CAN REMOVE AFTER WE UPGRADE TO VF 10.1.2
     * Login and receive a new token
     *
     * @return void
     */
    protected function renewTenantToken()
    {
        // If not using legacy authentication, see if the token has expired before trying to renew it
        if (!$this->useLegacyAuthentication() && !$this->checkTenantTokenExpired()) {
            $currentTime = gmdate('D, d-M-Y H:i:s T', strtotime('now'));
            $this->debug(
                'No need to renew token; not yet expired. ' . $currentTime . ' < ' . $this->tokenExpiration .
                'Username: ' . $this->config['API']['username'] . ' Token: ' . substr($this->token, 0, 30) . '...'
            );
            return;
        }
        $startTime = microtime(true);
        $this->token = null;
        $response = $this->performOkapiUsernamePasswordAuthentication(
            $this->config['API']['username'],
            $this->getSecretFromConfig($this->config['API'], 'password')
        );
        $this->setTokenValuesFromResponse($response);
        $endTime = microtime(true);
        $responseTime = $endTime - $startTime;
        $this->debug(
            'Token renewed in ' . $responseTime . ' seconds. Username: ' . $this->config['API']['username'] .
            ' Token: ' . substr($this->token, 0, 30) . '...'
        );
    }

    /**
     * MSUL -- CAN REMOVE AFTER UPGRADING TO VF 10.1.2
     * Check if our token is still valid. Return true if the token was already valid, false if it had to be renewed.
     *
     * Method taken from Stripes JS (loginServices.js:validateUser)
     *
     * @return bool
     */
    protected function checkTenantToken()
    {
        if ($this->useLegacyAuthentication()) {
            $response = $this->makeRequest('GET', '/users', [], [], [401, 403]);
            if ($response->getStatusCode() < 400) {
                return true;
            }
            // Clear token data to ensure that checkTenantTokenExpired triggers a renewal:
            $this->token = $this->tokenExpiration = null;
        }
        if ($this->checkTenantTokenExpired()) {
            $this->token = $this->tokenExpiration = null;
            $this->renewTenantToken();
            return false;
        }
        return true;
    }

    /**
     * MSUL -- CAN REMOVE AFTER UPGRADING TO VF 10.1.2
     * Check if our token has expired. Return true if it has expired, false if it has not.
     *
     * @return bool
     */
    protected function checkTenantTokenExpired()
    {
        return
            $this->token == null
            || $this->tokenExpiration == null
            || strtotime('now') >= strtotime($this->tokenExpiration);
    }

    /**
     * MSUL -- CAN REMOVE AFTER UPGRADING TO VF 10.1.2
     * Should we use a global cache for FOLIO API tokens?
     *
     * @return bool
     */
    protected function useGlobalTokenCache(): bool
    {
        // If we're configured to store user-specific tokens, we can't use the global
        // token cache.
        $useUserToken = $this->config['User']['use_user_token'] ?? false;
        return !$useUserToken && ($this->config['API']['global_token_cache'] ?? true);
    }

    /**
     * MSUL -- CAN REMOVE AFTER UPGRADING TO VF 10.1.2
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
        if ($this->token == null) {
            $this->renewTenantToken();
        } else {
            $this->checkTenantToken();
        }
    }

    /**
     * MSUL -- CAN REMOVE AFTER UPGRADING TO VF 10.1.2
     * Given a response from performOkapiUsernamePasswordAuthentication(),
     * extract the requested cookie.
     *
     * @param Response $response   Response from performOkapiUsernamePasswordAuthentication().
     * @param string   $cookieName Name of the cookie to get from the response.
     *
     * @return \Laminas\Http\Header\SetCookie
     */
    protected function getCookieByName(Response $response, string $cookieName): \Laminas\Http\Header\SetCookie
    {
        $folioUrl = $this->config['API']['base_url'];
        $cookies = new \Laminas\Http\Cookies();
        $cookies->addCookiesFromResponse($response, $folioUrl);
        $results = $cookies->getAllCookies();
        foreach ($results as $cookie) {
            if ($cookie->getName() == $cookieName) {
                return $cookie;
            }
        }
        throw new \Exception('Could not find ' . $cookieName . ' cookie in response');
    }

    /**
     * MSUL -- CAN REMOVE AFTER UPGRADING TO VF 10.1.2
     * Given a response from performOkapiUsernamePasswordAuthentication(),
     * extract and save authentication data we want to preserve.
     *
     * @param Response $response Response from performOkapiUsernamePasswordAuthentication().
     *
     * @return null
     */
    protected function setTokenValuesFromResponse(Response $response)
    {
        // If using legacy authentication, there is no option to renew tokens,
        // so assume the token is expired as of now
        if ($this->useLegacyAuthentication()) {
            $this->token = $response->getHeaders()->get('X-Okapi-Token')->getFieldValue();
            $this->tokenExpiration = gmdate('D, d-M-Y H:i:s T', strtotime('now'));
            $tokenCacheLifetime = 600; // cache old-fashioned tokens for 10 minutes
        } elseif ($cookie = $this->getCookieByName($response, 'folioAccessToken')) {
            $this->token = $cookie->getValue();
            $this->tokenExpiration = $cookie->getExpires();
            // cache RTR tokens using their known lifetime:
            $tokenCacheLifetime = strtotime($this->tokenExpiration) - strtotime('now');
        }
        if ($this->token != null && $this->tokenExpiration != null) {
            $this->sessionCache->folio_token = $this->token;
            $this->sessionCache->folio_token_expiration = $this->tokenExpiration;
            if ($this->useGlobalTokenCache()) {
                $this->putCachedData('token', [$this->token, $this->tokenExpiration], $tokenCacheLifetime);
            }
        } else {
            throw new \Exception('Could not find token data in response');
        }
    }

    /**
     * MSUL -- CAN REMOVE AFTER UPGRADING TO VF 10.1.2
     * Support method for patronLogin(): authenticate the patron with an Okapi
     * login attempt. Returns a CQL query for retrieving more information about
     * the authenticated user.
     *
     * @param string $username The patron username
     * @param string $password The patron password
     *
     * @return string
     */
    protected function patronLoginWithOkapi($username, $password)
    {
        $response = $this->performOkapiUsernamePasswordAuthentication($username, $password);
        $debugMsg = 'User logged in. User: ' . $username . '.';
        // We've authenticated the user with Okapi, but we only have their
        // username; set up a query to retrieve full info below.
        $query = 'username == ' . $username;
        // Replace admin with user as tenant if configured to do so:
        if ($this->config['User']['use_user_token'] ?? false) {
            $this->setTokenValuesFromResponse($response);
            $debugMsg .= ' Token: ' . substr($this->token, 0, 30) . '...';
        }
        $this->debug($debugMsg);
        return $query;
    }
}
