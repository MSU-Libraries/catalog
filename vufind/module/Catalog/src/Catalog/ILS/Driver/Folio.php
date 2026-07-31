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
use Catalog\Http\GuzzleLivePool;
use Catalog\Utils\RegexLookup as Regex;
use Generator;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use GuzzleHttp\Psr7;
use Laminas\Http\Header\HeaderInterface;
use Laminas\Http\Headers;
use Throwable;
use VuFind\Exception\ILS as ILSException;
use VuFind\Http\GuzzleServiceAwareInterface;
use VuFind\Http\GuzzleServiceAwareTrait;
use VuFind\ILS\Logic\AvailabilityStatus;

use function count;
use function in_array;
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
class Folio extends \VuFind\ILS\Driver\Folio implements GuzzleServiceAwareInterface
{
    use GuzzleServiceAwareTrait;

    /**
     * Configuration file reader object (PluginManager)
     *
     * @var \VuFind\Config\PluginManager
     */
    protected $configReader = null;

    /**
     * Guzzle client
     *
     * @var \GuzzleHttp\Client
     */
    protected $client;

    /**
     * Guzzle live pool
     *
     * @var \Catalog\Http\GuzzleLivePool
     */
    protected $pool;

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
        $configReader
    ) {
        $this->dateConverter = $dateConverter;
        $this->sessionFactory = $sessionFactory;
        $this->configReader = $configReader; // MSUL PC-1416 New param to read msul.ini
    }

    /**
     * Get the class' Guzzle client, instantiating it if needed
     *
     * @return Client
     */
    protected function getClient(): Client
    {
        return $this->client ??= $this->getGuzzleService()->createClient(
            $this->config['API']['base_url'],
            120
        );
    }

    /**
     * Get the class' Guzzle pool, instantiating it if needed
     *
     * @return GuzzleLivePool
     */
    protected function getPool(): GuzzleLivePool
    {
        return $this->pool ??= new GuzzleLivePool($this->getClient());
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
     * Make GET request async
     *
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
     * @param ?string           $baseUrl             Provide an alternate schema, host,
     * and optionally port to submit the request to (http://alt.example.edu:8080)
     * For async API to work propery, even across different hosts, they need to make
     * use of the same GuzzleHTTP client instance, so API calls to other endpoints
     * should use this function instead of instantiating their own client instnace.
     *
     * @return Promise\Promise for a Psr7\Response
     * @throws ILSException
     */
    public function makeRequestAsync(
        $path = '/',
        $params = [],
        $headers = [],
        $allowedFailureCodes = [],
        $debugParams = null,
        $attemptNumber = 1,
        $baseUrl = null
    ) {
        $req_headers = new Headers();
        $req_headers->addHeaders($headers);
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
        $folioBaseUrl = $this->config['API']['base_url'];
        $baseUrl ??= $folioBaseUrl;
        $logPath = ($folioBaseUrl != $baseUrl ? $baseUrl . $path : $path);
        $request = new Psr7\Request('GET', $baseUrl . $path);

        if ($this->logger) {
            $this->debugRequest('GET', $path, $debugParams ?? $params, $headers);
        }

        $this->debug('Request ASYNC start for path ' . $logPath);
        $startTime = microtime(true);
        $promise = $this->getPool()->add(
            $request,
            ['headers' => $req_headers->toArray(), 'query' => $params]
        );
        return $promise->then(
            function (Psr7\Response $response) use (
                $startTime,
                $path,
                $params,
                $headers,
                $allowedFailureCodes,
                $debugParams,
                $attemptNumber,
                $baseUrl,
                $logPath
            ) {
                $endTime = microtime(true);
                $responseTime = $endTime - $startTime;
                $this->debug('Request ASYNC time to unwrap --- ' . $responseTime . ' seconds for ' . $logPath);
                $code = $response->getStatusCode();
                if (
                    !($code >= 200 && $code < 300)
                    && !$this->failureCodeIsAllowed($code, $allowedFailureCodes)
                ) {
                    $this->logError(
                        "Unexpected error response (attempt #$attemptNumber"
                        . "); code: {$code}, body: {$response->getBody()}"
                    );
                    if ($this->shouldRetryAfterUnexpectedStatusCode($response, $attemptNumber)) {
                        return $this->makeRequestAsync(
                            $path,
                            $params,
                            $headers,
                            $allowedFailureCodes,
                            $debugParams,
                            $attemptNumber + 1,
                            $baseUrl
                        );
                    } else {
                        throw new ILSException('Unexpected error code.');
                    }
                }
                return $response;
            },
            function (Throwable $e) {
                $this->logError('Unexpected ' . $e::class . ': ' . (string)$e);
                throw new ILSException('Error during send operation.');
            }
        );
    }

    /**
     * MSUL - PC-1659: Add support for async calls
     * Helper function to retrieve a single page of results from FOLIO API
     *
     * @param string $interface FOLIO api interface to call
     * @param array  $query     Extra GET parameters (e.g. ['query' => 'your cql here'])
     * @param int    $offset    Starting record index
     * @param int    $limit     Max number of records to retrieve
     *
     * @return Promise\Promise for array
     * @throws ILSException if the response code is not a success or the response is not JSON
     */
    protected function getResultPage($interface, $query = [], $offset = 0, $limit = 1000)
    {
        $combinedQuery = array_merge($query, compact('offset', 'limit'));
        $promise = $this->makeRequestAsync(
            $interface,
            $combinedQuery
        );
        return $promise->then(
            function (Psr7\Response $response) use ($interface) {
                $json = json_decode($response->getBody());
                $code = $response->getStatusCode();
                if (!($code >= 200 && $code < 300) || !$json) {
                    $msg = $json->errors[0]->message ?? json_last_error_msg();
                    throw new ILSException("Error: '$msg' fetching from '$interface'");
                }
                return $json;
            }
        );
    }

    /**
     * MSUL - PC-1659: Add support for async calls
     * Helper function to retrieve paged results from FOLIO API
     *
     * @param string $responseKey Key containing values to collect in response
     * @param string $interface   FOLIO api interface to call
     * @param array  $query       Extra GET parameters (e.g. ['query' => 'your cql here'])
     * @param int    $limit       How many results to retrieve from FOLIO per call
     *
     * @return Generator<int,mixed>
     * @throws ILSException if there is an issue with the response
     */
    protected function getPagedResults($responseKey, $interface, $query = [], $limit = 1000)
    {
        // Make a promise immediately, so the call beings even prior to generator iteration
        $promises = [$this->getResultPage($interface, $query, 0, $limit)];

        $gen = function ($responseKey, $interface, $query, $limit) use ($promises) {
            $offset = $limit;
            $totalEstimate = 1;
            while ($promises || ($offset <= $totalEstimate)) {
                if ($offset <= $totalEstimate) {
                    $promises[] = $this->getResultPage($interface, $query, $offset, $limit);
                    $offset += $limit;
                } elseif ($promises) {
                    // Unwrap current promises until we get a greater estimate
                    $json = array_shift($promises)->wait();
                    $totalEstimate = $json->totalRecords ?? 0;
                    foreach ($json->$responseKey ?? [] as $item) {
                        yield $item ?? '';
                    }
                }
            }
        };
        return $gen($responseKey, $interface, $query, $limit);
    }

    /**
     * Get FOLIO records by batches of ids.
     * When using a unique field for $idField (such as 'id'), this function does not check
     * if all records are found, and returned records are not guaranteed to be in the order of the given ids.
     *
     * @param string[] $ids         ids to look for in the records
     * @param string   $idField     field to compare to given ids
     * @param string   $responseKey response key with the records to retrieve
     * @param string   $endpoint    FOLIO API endpoint
     * @param string   $querySuffix optional string to append to the queries
     *
     * @return Generator<int,mixed>
     * @throws ILSException if there is an issue with the FOLIO response
     */
    protected function getByBatch($ids, $idField, $responseKey, $endpoint, $querySuffix = '')
    {
        $cachedItems = [];
        $idToKey = fn ($id) => $endpoint . '[' . $idField . '=' . $id . ']';
        $idsToLookFor = [];
        foreach ($ids as $id) {
            $items = $this->getCachedData($idToKey($id));
            if ($items == null) {
                $idsToLookFor[] = $id;
            } else {
                $cachedItems = array_merge($cachedItems, $items);
            }
        }
        $fnSafeQuery = function ($idField, $idsInBatch, $querySuffix) {
            $idsWithQuotes = array_map(fn ($id) => '"' . $this->escapeCql($id) . '"', $idsInBatch);
            return [
                'query' => $idField . ' == (' . implode(' OR ', $idsWithQuotes) . ')' . $querySuffix,
            ];
        };
        $idChunks = array_chunk($idsToLookFor, static::QUERY_BY_IDS_BATCH_SIZE);
        if (count($idChunks) == 0) {
            $gen = function () use ($cachedItems) {
                yield from $cachedItems;
            };
            return $gen();
        }

        $pagedResults = $this->getPagedResults(
            $responseKey,
            $endpoint,
            $fnSafeQuery($idField, array_shift($idChunks), $querySuffix)
        );
        $gen = function (
            $idField,
            $responseKey,
            $endpoint,
            $querySuffix
        ) use (
            $cachedItems,
            $idChunks,
            $pagedResults,
            $fnSafeQuery,
            $idToKey
        ) {
            yield from $cachedItems;
            $resultsToCache = [];
            while (true) {
                foreach ($pagedResults as $item) {
                    $key = $idToKey($item->$idField);
                    if (isset($resultsToCache[$key])) {
                        $resultsToCache[$key][] = $item;
                    } else {
                        $resultsToCache[$key] = [$item];
                    }
                    yield $item;
                }
                if (count($idChunks) == 0) {
                    break;
                }
                $pagedResults = $this->getPagedResults(
                    $responseKey,
                    $endpoint,
                    $fnSafeQuery($idField, array_shift($idChunks), $querySuffix)
                );
            }
            foreach ($resultsToCache as $key => $items) {
                $this->putCachedData($key, $items);
            }
        };
        return $gen($idField, $responseKey, $endpoint, $querySuffix);
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
            return;
        }
        $querySuffix = ' NOT discoverySuppress==true';
        yield from $this->getByBatch(
            $instanceIds,
            'instanceId',
            'holdingsRecords',
            '/holdings-storage/holdings',
            $querySuffix
        );
    }

    /**
     * Support method for getHoldings() -- retrieve items by holding ids (including bound-with items)
     *
     * @param string[] $holdingsIds the FOLIO holdings ids
     *
     * @return Generator<int,mixed> The items, with an additional queryHoldingsRecordId property with
     *                              the matching holdings id
     * @throws ILSException if there is an issue with the FOLIO response
     */
    protected function getItemsByHoldingIds(array $holdingsIds)
    {
        if (count($holdingsIds) == 0) {
            return;
        }
        $folioItemSort = $this->config['Holdings']['folio_sort'] ?? '';
        $querySuffix = empty($folioItemSort) ? '' : ' sortby ' . $folioItemSort;
        if (count($holdingsIds) == 1) {
            // /inventory/items-by-holdings-id returns bound-with items too (but it only takes one holdingsRecordId)
            foreach (
                $this->getByBatch(
                    $holdingsIds,
                    'holdingsRecordId',
                    'items',
                    '/inventory/items-by-holdings-id',
                    $querySuffix
                ) as $item
            ) {
                $item->queryHoldingsRecordId = $holdingsIds[0];
                yield $item;
            }
            return;
        }
        // Retrieve the item records
        $holdingsItemIds = [];
        foreach ($holdingsIds as $holdingsId) {
            $holdingsItemIds[$holdingsId] = [];
        }
        foreach (
            $this->getByBatch(
                $holdingsIds,
                'holdingsRecordId',
                'items',
                '/inventory/items',
                $querySuffix
            ) as $item
        ) {
            $holdingsId = $item->holdingsRecordId;
            $item->queryHoldingsRecordId = $holdingsId;
            $holdingsItemIds[$holdingsId][] = $item->id;
            yield $item;
        }
        // Retrieve the related bound-with items
        // Duplicate items are avoided for each holdings
        $boundWithItemIds = [];
        $itemIdToHoldingsRecordId = [];
        foreach (
            $this->getByBatch(
                $holdingsIds,
                'holdingsRecordId',
                'boundWithParts',
                '/inventory-storage/bound-with-parts',
                $querySuffix
            ) as $boundWithPart
        ) {
            $itemId = $boundWithPart->itemId;
            $holdingsId = $boundWithPart->holdingsRecordId;
            if (in_array($itemId, $holdingsItemIds[$holdingsId])) {
                continue;
            }
            $holdingsItemIds[$holdingsId][] = $itemId;
            $boundWithItemIds[] = $itemId;
            $itemIdToHoldingsRecordId[$itemId] = $holdingsId;
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
            yield $item;
        }
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
     * @param ?array     $customData       Additional data to process into the returned array
     *
     * @return array
     */
    protected function msulFormatHoldingItem(
        string $bibId,
        array $holdingDetails,
        $item,
        $number,
        string $dueDateValue,
        $boundWithRecords,
        $currentLoan,
        $customData = null
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
        // MSUL PC-1416, PC-1636
        $locationName = $this->getLocationData($locationId)['name'];
        $locAndHoldings = array_merge(
            $locAndHoldings,
            $this->processCustomData(
                $bibId,
                $locationName,
                $callNumberData['callnumber'],
                $customData
            )
        );
        return $callNumberData + $locAndHoldings + [
            'id' => $bibId,
            'item_id' => $item->id,
            'holdings_id' => $holdingDetails['id'],
            'number' => $number,
            'enumchron' => $enum,
            'barcode' => $item->barcode ?? '',
            'status' => $item->status->name,
            'duedate' => $dueDateValue,
            'availability' => new AvailabilityStatus(
                $item->status->name == 'Available',
                $item->status->name,
            ),
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
     * Get all bib records bound-with this item, including
     * the directly-linked bib record and its title.
     *
     * @param object $item The item record
     *
     * @return Promise\Promise which unwraps to an array of arrays with 'title' and 'bibId'
     */
    protected function getBoundWithRecordsPromise($item): Promise\Promise
    {
        $path = '/inventory/items/' . $item->id;
        return $this->makeRequestAsync($path)->then(
            function (Psr7\Response $response) use ($path) {
                $boundWithRecords = [];
                $item = json_decode($response->getBody());
                $code = $response->getStatusCode();
                if (!($code >= 200 && $code < 300) || !$item) {
                    $msg = $item->errors[0]->message ?? json_last_error_msg();
                    throw new ILSException("Error: '$msg' fetching from '$path'");
                }
                foreach ($item->boundWithTitles ?? [] as $boundWithTitle) {
                    $boundWithRecords[] = [
                        'title' => $boundWithTitle->briefInstance?->title,
                        'bibId' => $this->getBibId($boundWithTitle->briefInstance),
                    ];
                }
                return $boundWithRecords;
            }
        );
    }

    /**
     * Gather API data to later be used by processInstanceHoldings(). Specifically, these
     * are GuzzleHTTP promises for API data which will be needed later. By queuing
     * promises for data, we can reduce the amount of API wait time later.
     *
     * @param object $item             The item record
     * @param string $bibId            Current bibliographic ID
     * @param string $callNumber       The call number
     * @param string $locationCode     The location code
     * @param int    $dueDateItemCount Number of times getCurrentLoan()/getDueDate() were called
     *                                 (passed by reference)
     *
     * @return array An array of data containing promises in keys:
     *               'boundWith', 'currentLoan', 'customData'
     */
    protected function gatherItemPromises(
        $item,
        $bibId,
        $callNumber,
        $locationCode,
        &$dueDateItemCount
    ): array {
        $boundWithPromise = new Promise\FulfilledPromise([]);
        if ($item->isBoundWith ?? false) {
            $boundWithPromise = $this->getBoundWithRecordsPromise($item);
        }

        $currentLoanPromise = new Promise\FulfilledPromise([]);
        $showDueDate = $this->config['Availability']['showDueDate'] ?? true;
        $maxNumDueDateItems = $this->config['Availability']['maxNumberItems'] ?? 5;
        if (
            $item->status->name == 'Checked out'
            && $showDueDate
            && $dueDateItemCount < $maxNumDueDateItems
        ) {
            $currentLoanPromise = $this->getCurrentLoanPromises($item->id);
            $dueDateItemCount++;
        }

        $promises = [
            'boundWith' => $boundWithPromise,
            'currentLoan' => $currentLoanPromise,
            'customData' => $this->customDataPromise($bibId, $callNumber, $locationCode),
        ];
        return $promises;
    }

    /**
     * Support method for getHoldings() -- processes a FOLIO item
     *
     * @param string $bibId          Bib-level id
     * @param array  $holdingDetails details for the holding
     * @param object $item           item to process
     * @param array  $itemPromises   An associative array with Promises contained within
     * @param int    $number         item number
     *
     * @return array An associative array
     */
    protected function msulProcessItem(
        $bibId,
        $holdingDetails,
        $item,
        $itemPromises,
        $number
    ): array {
        $copyNumber = $item->copyNumber ?? null; // MSU
        $showTime = $this->config['Availability']['showTime'] ?? false;
        $currentLoan = null;
        $dueDateValue = '';
        $boundWithPromise = $itemPromises['boundWith'];
        $currentLoanPromise = $itemPromises['currentLoan'];
        $customDataPromise = $itemPromises['customData'];
        $currentLoan = $this->getCurrentLoan($item->id, $currentLoanPromise);
        $dueDateValue = $currentLoan ? $this->getDueDate($currentLoan, $showTime) : '';
        $nextItem = $this->msulFormatHoldingItem(
            $bibId,
            $holdingDetails,
            $item,
            $copyNumber, // MSU, use copyNumber instead of number
            $dueDateValue,
            $boundWithPromise->wait(),
            $currentLoan,
            $customDataPromise->wait()
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
        // Ensure locations API is cached to avoid potential delay when unwrapping promises
        $this->getLocations();
        /**
         * Pass 1: Queue up API call promises
         */
        $holdingsPromises = [];
        foreach ($holdings as $holding) {
            $folioItemsForHolding = array_filter(
                $folioItems,
                fn ($item) => $item->queryHoldingsRecordId == $holding->id
            );
            $holdingDetails = $this->getHoldingDetailsForItem($holding);
            $itemsPromises = [];
            foreach ($folioItemsForHolding as $item) {
                if ($item->discoverySuppress ?? false) {
                    continue;
                }
                $callNumberData = $this->chooseCallNumber(
                    $holdingDetails['holdingCallNumberPrefix'],
                    $holdingDetails['holdingCallNumber'],
                    $item->effectiveCallNumberComponents->prefix
                        ?? $item->itemLevelCallNumberPrefix ?? '',
                    $item->effectiveCallNumberComponents->callNumber
                        ?? $item->itemLevelCallNumber ?? ''
                );
                $locationCode = $this->getLocationData($item->effectiveLocation->id)['code'];
                $itemsPromises[] = [
                    'item' => $item,
                    'promises' => $this->gatherItemPromises(
                        $item,
                        $bibId,
                        $callNumberData['callnumber'],
                        $locationCode,
                        $dueDateItemCount
                    ),
                ];
            }
            $holdingsPromises[] = [
                'holding' => $holding,
                'holdingDetails' => $holdingDetails,
                'itemsPromises' => $itemsPromises,
            ];
        }
        /**
         * Pass 2: Unwrap API calls and process them
         */
        foreach ($holdingsPromises as $holdingPromises) {
            $number = 0;
            $nextBatch = [];
            $sortNeeded = false;
            $holding = $holdingPromises['holding'];
            $holdingDetails = $holdingPromises['holdingDetails'];
            foreach ($holdingPromises['itemsPromises'] as $itemPromises) {
                $item = $itemPromises['item'];
                $number++;
                $nextItem = $this->msulProcessItem(
                    $bibId,
                    $holdingDetails,
                    $item,
                    $itemPromises['promises'],
                    $number
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
            $instanceIds = [];
            foreach ($instances as $instance) {
                $instanceIds[] = $instance->id;
                $bibIdToInstanceId[$instance->$idType] = $instance->id;
            }
        }

        $holdings = [];
        $holdingIds = [];
        foreach ($this->getHoldingsByInstanceIds($instanceIds) as $holding) {
            $holdings[] = $holding;
            $holdingIds[] = $holding->id;
        }

        $folioItems = [...$this->getItemsByHoldingIds($holdingIds)];
        $results = [];
        foreach ($bibIds as $bibId) {
            $instanceId = $bibIdToInstanceId[$bibId];
            $holdingsForInstance = array_filter($holdings, fn ($holding) => $holding->instanceId == $instanceId);
            $results[] = $this->processInstanceHoldings($bibId, $holdingsForInstance, $folioItems);
        }
        return $results;
    }

    /**
     * Support method for getHoldings(): obtaining the Due Date from the
     * current loan, adjusting the timezone and formatting in universal
     * time with or without due time
     *
     * @param \stdClass $loan     The current loan
     * @param bool      $showTime Determines if date or date & time is returned
     *
     * @return string
     */
    protected function getDueDate($loan, $showTime)
    {
        $dueDate = $this->getDateTimeFromString($loan->dueDate);
        $method = $showTime
            ? 'convertToDisplayDateAndTime' : 'convertToDisplayDate';
        return $this->dateConverter->$method('U', $dueDate->format('U'));
    }

    /**
     * Support method for getHoldings(): obtaining any current loan from OKAPI
     * by calling /circulation/loans with the item->id
     *
     * @param string $itemId ID for the item to query
     *
     * @return Generator<int,mixed>
     */
    protected function getCurrentLoanPromises(string $itemId): Generator
    {
        $query = 'itemId==' . $itemId . ' AND status.name==Open';
        $pagedResults = $this->getPagedResults(
            'loans',
            '/circulation/loans',
            compact('query')
        );
        $gen = function () use ($pagedResults) {
            yield from $pagedResults;
        };
        return $gen();
    }

    /**
     * Support method for getHoldings(): obtaining any current loan from OKAPI
     * by calling /circulation/loans with the item->id
     *
     * @param string                     $itemId       ID for the item to query
     * @param ?iterable<Promise\Promise> $loanPromises An iterable of Promises for loans;
     *                                                 If null, will generate Promises itself
     *
     * @return \stdClass|void
     */
    protected function getCurrentLoan($itemId, $loanPromises = null)
    {
        if ($loanPromises === null) {
            $loanPromises = $this->getCurrentLoanPromises($itemId);
        }
        foreach ($loanPromises as $loan) {
            // many loans are returned for an item, the one we want
            // is the one without a returnDate
            if (!isset($loan->returnDate) && isset($loan->dueDate)) {
                return $loan;
            }
        }
        return null;
    }

    /**
     * MSU PC-1628 Use PIN authentication for patron login
     * Support method for patronLogin(): authenticate the patron with an Okapi
     * login attempt. Returns a CQL query for retrieving more information about
     * the authenticated user.
     *
     * @param string  $username The patron username
     * @param ?string $password The patron password
     *
     * @return string
     */
    protected function patronLoginWithOkapi($username, $password)
    {
        $response = $this->performOkapiPINAuthentication($username, $password); // MSU
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

    /**
     * MSU PC-1628 pass id to patron login function
     * Patron Login
     *
     * This is responsible for authenticating a patron against the catalog.
     *
     * @param string $username The patron username
     * @param string $password The patron password
     *
     * @return mixed Associative array of patron info on successful login,
     * null on unsuccessful login.
     */
    public function patronLogin($username, $password)
    {
        $profile = null;
        $doOkapiLogin = $this->config['User']['okapi_login'] ?? false;
        $usernameField = $this->config['User']['username_field'] ?? 'username';

        // If the username field is not the default 'username' we will need to
        // do a lookup to find the correct username value for Okapi login. We also
        // need to do this lookup if we're skipping Okapi login entirely.
        if (!$doOkapiLogin || $usernameField !== 'username') {
            $query = $this->getUserWithCql($username, $password);
            $profile = $this->fetchUserWithCql($query);
            if ($profile === null) {
                return null;
            }
        }

        // If we need to do an Okapi login, we have the information we need to do
        // it at this point.
        if ($doOkapiLogin) {
            try {
                // If we fetched the profile earlier, we want to use the username
                // from there; otherwise, we'll use the passed-in version.
                $query = $this->patronLoginWithOkapi(
                    $profile->id ?? $username, // MSU pass id instead of username PC-1628
                    $password
                );
            } catch (\Exception $e) {
                return null;
            }
            // If we didn't load a profile earlier, we should do so now:
            if (!isset($profile)) {
                $profile = $this->fetchUserWithCql($query);
                if ($profile === null) {
                    return null;
                }
            }
        }

        return [
            'id' => $profile->id,
            'username' => $username,
            'cat_username' => $username,
            'cat_password' => $password,
            'firstname' => $profile->personal->firstName ?? null,
            'lastname' => $profile->personal->lastName ?? null,
            'email' => $profile->personal->email ?? null,
            'addressTypeIds' => array_map(
                fn ($address) => $address->addressTypeId,
                $profile->personal->addresses ?? []
            ),
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
        $resultPage = $this->getResultPage('/circulation/loans', compact('query'), $offset, $limit)->wait();
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
                // MSU remove prefix so ilsDetails in templates populates
                'id' => $this->getBibIdWithoutPrefix($trans->item->instanceId),
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
     * MSU can remove when PR 5001 is in a release
     * Get latest major version of a $moduleName enabled for a tenant.
     * Result is cached.
     *
     * @param string $moduleName module name
     *
     * @return int module version or 0 if no module found
     */
    protected function getModuleMajorVersion(string $moduleName): int
    {
        $cacheKey = 'module_version:' . $moduleName;
        $version = $this->getCachedData($cacheKey);
        if ($version === null) {
            // Get latest version of a module enabled for a tenant.
            // Allow errors to not trigger an exception because that means we need to try the
            // next call that is compatible with pre-Sunflower.
            $response = $this->makeRequest(
                'GET',
                '/modules/discovery?query=(name==' . $moduleName . ')',
                allowedFailureCodes:[400, 403, 404, 500]
            );

            // If there was a failure with the first method, attempt the second
            // endpoint to get the version.
            $json = json_decode($response->getBody(), true);
            if (empty($json) || isset($json['errors'])) {
                $response = $this->makeRequest(
                    'GET',
                    '/_/proxy/tenants/' . $this->tenant . '/modules?filter=' . $moduleName . '&latest=1',
                );
                $json = json_decode($response->getBody(), true);
                $latest = $json[0]['id'] ?? '0';
            } else {
                $latest = $json['discovery'][0]['id'] ?? '0';
            }

            // get version major from json result
            preg_match_all('!\d+!', $latest, $matches);
            $version = (int)($matches[0][0] ?? 0);
            if ($version === 0) {
                $this->debug('Unable to find version in ' . $response->getBody());
            } else {
                // Only cache non-zero values, so we don't persist an error condition:
                $this->putCachedData($cacheKey, $version);
            }
        }
        return $version;
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
                    $this->debug('Could not identify the correct package among several publishers, ' .
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

    /**
     * MSU-only method for PC-1628
     * Support method to perform a PIN login to Okapi.
     *
     * @param string $id  The patron id
     * @param string $pin The patron pin
     *
     * @return \Laminas\Http\Response
     */
    protected function performOkapiPINAuthentication(string $id, string $pin): \Laminas\Http\Response
    {
        $credentials = compact('id', 'pin');
        $headers = [
            'Accept: text/plain',
        ];
        // Get token
        return $this->makeRequest(
            method: 'POST',
            path: '/patron-pin/verify',
            params: json_encode($credentials),
            debugParams: '{"id":"...","pin":"..."}',
            headers: $headers
        );
    }

    /**
     * MSU-only method for PC-1584 to return the bibId without the mutli-backend prefix.
     *
     * @param string $instanceOrInstanceId Instance object or ID (will be looked up
     * using holding or item ID if not provided)
     * @param string $holdingId            Holding-level id (optional)
     * @param string $itemId               Item-level id (optional)
     *
     * @return string Appropriate bib id retrieved from FOLIO identifiers
     */
    protected function getBibIdWithoutPrefix(
        $instanceOrInstanceId = null,
        $holdingId = null,
        $itemId = null
    ) {
        $fullBibId = $this->getBibId($instanceOrInstanceId, $holdingId, $itemId);
        return substr($fullBibId, strpos($fullBibId, '.') + 1);
    }

    // MSUL PC-1416, PC-1636: Attempt to get the location data from helm if we have a callnumber
    /**
     * MSU-only for gathering custom location data for use with location mapping.
     *
     * @param string $bibId        Current bibliographic ID
     * @param string $callNumber   The call number
     * @param string $locationCode The location code
     *
     * @return Promise\Promise A promise for location data; may contain null if no config found
     *                         or callnumber empty
     */
    protected function customDataPromise(
        $bibId,
        $callNumber,
        $locationCode
    ) {
        $promise = new Promise\FulfilledPromise(null);

        $msulConfig = $this->configReader->get('msul');
        if (empty($callNumber) || !isset($msulConfig)) {
            return $promise;
        }

        $apiUrl = $msulConfig['Locations']['api_url'] ?? '';
        $parsed = parse_url($apiUrl);
        if (!empty($apiUrl) && isset($parsed['scheme'], $parsed['host'], $parsed['path'])) {
            $baseUrl = $parsed['scheme'] . '://' . $parsed['host'];
            if (isset($parsed['port'])) {
                $baseUrl .= ':' . $parsed['port'];
            }
            $path = $parsed['path'];
            $query = $parsed['query'] ?? '';

            // Replace %%callnumber%% and %%loc%% with the real callnumber and location code
            $query = str_replace('%%callnumber%%', urlencode($callNumber), $query);
            $query = str_replace('%%loc%%', urlencode($locationCode), $query);
            parse_str($query, $params);
            $apiUrl = $baseUrl . $path . $query;

            $data = $this->getCachedData($apiUrl);
            if ($data !== null) {
                $promise = new Promise\FulfilledPromise($data);
            } else {
                $promise = $this->makeRequestAsync($path, $params, baseUrl: $baseUrl)->then(
                    function (Psr7\Response $response) use ($apiUrl) {
                        $data = json_decode($response->getBody());
                        if ($data === null) {
                            return null;
                        }
                        $this->putCachedData($apiUrl, $data);
                        return $data;
                    },
                    function (Throwable $e) use ($bibId, $callNumber, $locationCode) {
                        $this->logWarning(
                            'Could not get location data for callnumber '
                            . $callNumber . ' (' . $bibId . ')'
                            . ' and location code ' . $locationCode
                        );
                        return null;
                    }
                );
            }
        }
        return $promise;
    }

    // MSUL PC-1416, PC-1636: Attempt to get the location data from helm if we have a callnumber
    /**
     * MSU-only for processing API promise into custom data for use with location mapping.
     *
     * @param string  $bibId        Current bibliographic ID
     * @param string  $locationName The location name
     * @param string  $callNumber   The call number
     * @param ?object $data         Decoded JSON from the API call to HELM
     *
     * @return array An array of customized location data
     */
    protected function processCustomData(
        string $bibId,
        string $locationName,
        string $callNumber,
        ?object $data
    ): array {
        if ($data === null) {
            return [];
        }
        $msulConfig = $this->configReader->get('msul');  // Known safe from customDataPromise()
        $topKey = $msulConfig['Locations']['response_top_key'] ?? 'callnumbers';
        $floorKey = $msulConfig['Locations']['response_floor_key'] ?? '';
        $notMappableFloor = $msulConfig['Locations']['not_mappable_floor_value'] ?? '';
        $gisFloorKey = $msulConfig['Locations']['response_gis_floor_key'] ?? '';
        $locationKey = $msulConfig['Locations']['response_location_key'] ?? '';

        // Parse the response and add to our location results
        $customizedLoc = [];
        if (isset($data->$topKey) && count($data->$topKey) >= 1) {
            $floor = $floorKey ? ($data->$topKey[0]->$floorKey ?? '') : '';
            $gisFloor = $gisFloorKey ? ($data->$topKey[0]->$gisFloorKey ?? '') : '';
            $location = $locationKey ? ($data->$topKey[0]->$locationKey ?? '') : '';

            // Handle when 'Not Mappable' floor is set
            if ($floor == $notMappableFloor) {
                $floor = '';
                $gisFloor = '';
            }

            $floorPart = !empty($floor) ? ' - ' . $floor : '';
            $locationPart = !empty($location) ? '(' . $location . ')' : '';
            $combinedPart = $floorPart . ' ' . $locationPart;

            if (!empty(trim($combinedPart))) {
                $customizedLoc['location'] = trim($locationName . $combinedPart);
                $this->debug(
                    'Found additional location data for callnumber ' . $callNumber .
                    ' (' . $bibId . ')' . '. Updating location to: ' . $locationName
                );
            }
            if (!empty(trim($location))) {
                $customizedLoc['msulLocation'] = $location;
                $this->debug(
                    'Adding ' . $location . ' to msulLocation for callnumber ' . $callNumber
                );
            }
            if (!empty(trim($gisFloor))) {
                $customizedLoc['gisfloor'] = $gisFloor;
                $this->debug(
                    'Adding ' . $gisFloor . ' to gisfloor for callnumber ' . $callNumber
                );
            }
        } else {
            $this->debug(
                'No data found for callnumber ' . $callNumber . ' (' . $bibId . ')'
            );
            $this->debug(var_export($data, true));
        }
        return $customizedLoc;
    }
}
