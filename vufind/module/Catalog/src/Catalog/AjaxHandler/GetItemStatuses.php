<?php

namespace Catalog\AjaxHandler;

use Laminas\Mvc\Controller\Plugin\Params;
use VuFind\Exception\ILS as ILSException;

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
                        // MSUL: Alternate message on failure
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
        // array with a key for every requested ID.  We will clear keys as we
        // encounter IDs in the response -- anything left will be problems that
        // need special handling.
        $missingIds = array_flip($ids);

        // Load messages for response:
        $messages = [
            'available' => $this->renderer->render('ajax/status-available.phtml'),
            'unavailable' =>
                $this->renderer->render('ajax/status-unavailable.phtml'),
            'unknown' => $this->renderer->render('ajax/status-unknown.phtml'),
        ];

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
                    $current = $this
                        ->getItemStatusError($record, $messages['unknown']);
                } elseif ($locationSetting === 'group') {
                    $current = $this->getItemStatusGroup(
                        $record,
                        $messages,
                        $callnumberSetting
                    );
                } else {
                    $current = $this->getItemStatus(
                        $record,
                        $messages,
                        $locationSetting,
                        $callnumberSetting
                    );
                }
                // If a full status display has been requested and no errors were
                // encountered, append the HTML:
                if ($showFullStatus && empty($record[0]['error'])) {
                    $current['full_status'] = $this->renderFullStatus(
                        $record,
                        compact('searchId')
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
            $statuses[] = [
                'id'                   => $missingId,
                'availability'         => 'false',
                'availability_message' => $messages['unavailable'],
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
