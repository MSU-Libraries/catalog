<?php

/**
 * "Get Item Status" AJAX handler
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018.
 * Copyright (C) The National Library of Finland 2023.
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
 * @package  Ajax_Handler
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Catalog\AjaxHandler;

use Laminas\Mvc\Controller\Plugin\Params;
use VuFind\Exception\ILS as ILSException;
use VuFind\ILS\Logic\AvailabilityStatusInterface;

use function count;
use function is_array;

/**
 * "Get Item Status" AJAX handler
 *
 * This is responsible for printing the holdings information for a
 * collection of records in JSON format.
 *
 * @category VuFind
 * @package  Ajax_Handler
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetItemStatuses extends \VuFind\AjaxHandler\GetItemStatuses
{
    /**
     * Handle a request.
     *
     * @param Params $params Parameter helper from controller
     *
     * @return array [response data, HTTP status code]
     */
    public function handleRequest(Params $params)
    {
        $results = [];
        $this->disableSessionWrites();  // avoid session write timing bug
        $ids = $params->fromPost('id') ?? $params->fromQuery('id', []);
        $searchId = $params->fromPost('sid') ?? $params->fromQuery('sid');
        try {
            $results = $this->ils->getStatuses($ids);
        } catch (ILSException $e) {
            // If the ILS fails, send an error response instead of a fatal
            // error; we don't want to confuse the end user unnecessarily.
            error_log($e->getMessage());
            foreach ($ids as $id) {
                $results[] = [
                    [
                        'id' => $id,
                        // MSUL - alternate message on failure
                        'error' => 'Holding data is currently unavailable.',
                    ],
                ];
            }
        }

        if (!is_array($results)) {
            // If getStatuses returned garbage, let's turn it into an empty array
            // to avoid triggering a notice in the foreach loop below.
            $results = [];
        }

        // In order to detect IDs missing from the status response, create an
        // array with a key for every requested ID. We will clear keys as we
        // encounter IDs in the response -- anything left will be problems that
        // need special handling.
        $missingIds = array_flip($ids);

        // Load callnumber and location settings:
        $callnumberSetting = $this->config->Item_Status->multiple_call_nos ?? 'msg';
        $locationSetting = $this->config->Item_Status->multiple_locations ?? 'msg';
        $showFullStatus = $this->config->Item_Status->show_full_status ?? false;

        // Loop through all the status information that came back
        $statuses = [];
        foreach ($results as $recordNumber => $record) {
            // Filter out suppressed locations:
            $record = $this->filterSuppressedLocations($record);

            // Skip empty records:
            if (count($record)) {
                // Check for errors
                if (!empty($record[0]['error'])) {
                    $unknownStatus = $this->availabilityStatusManager->createAvailabilityStatus(
                        AvailabilityStatusInterface::STATUS_UNKNOWN
                    );
                    $current = $this
                        ->getItemStatusError(
                            $record,
                            $this->getAvailabilityMessage($unknownStatus)
                        );
                } elseif ($locationSetting === 'group') {
                    $current = $this->getItemStatusGroup(
                        $record,
                        $callnumberSetting
                    );
                } else {
                    $current = $this->getItemStatus(
                        $record,
                        $locationSetting,
                        $callnumberSetting
                    );
                }
                // If a full status display has been requested and no errors were
                // encountered, append the HTML:
                if ($showFullStatus && empty($record[0]['error'])) {
                    $current['full_status'] = $this->renderFullStatus(
                        $record,
                        $current,
                        compact('searchId', 'current'),
                    );
                }
                $current['record_number'] = array_search($current['id'], $ids);
                $statuses[] = $current;

                // The current ID is not missing -- remove it from the missing list.
                unset($missingIds[$current['id']]);
            }
        }

        // If any IDs were missing, send back appropriate dummy data
        foreach ($missingIds as $missingId => $recordNumber) {
            $availabilityStatus = $this->availabilityStatusManager->createAvailabilityStatus(false);
            $statuses[] = [
                'id'                   => (string)$missingId, // array_flip may have converted to int
                'availability'         => 'false',
                'availability_message' => $this->getAvailabilityMessage($availabilityStatus),
                'location'             => $this->translate('Unknown'),
                'locationList'         => false,
                'reserve'              => 'false',
                'reserve_message'      => $this->translate('Not On Reserve'),
                'callnumber'           => '',
                'missing_data'         => true,
                'record_number'        => $recordNumber,
            ];
        }

        // Done
        return $this->formatResponse(compact('statuses'));
    }
}
