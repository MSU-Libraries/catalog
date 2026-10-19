<?php

/**
 * EDS API Backend.
 *
 * PHP version 8
 *
 * Copyright (C) EBSCO Industries 2013
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
 * @package  Search
 * @author   Michelle Milton <mmilton@epnet.com>
 * @author   Cornelius Amzar <cornelius.amzar@bsz-bw.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace Catalog\Backend\EDS;

use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\ParamBag;
use VuFindSearch\Backend\Exception\BackendException;
use VuFindSearch\Backend\EDS\ApiException;
use Exception;

/**
 *  EDS API Backend.
 *
 * @category VuFind
 * @package  Search
 * @author   Michelle Milton <mmilton@epnet.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class Backend extends \VuFindSearch\Backend\EDS\Backend
{
    /**
     * MSUL PC-1761 Avoid 129 errors being logged as critical errors
     * Perform a search and return record collection.
     *
     * @param AbstractQuery $query  Search query
     * @param int           $offset Search offset
     * @param int           $limit  Search limit
     * @param ?ParamBag     $params Search backend parameters
     *
     * @return \VuFindSearch\Response\RecordCollectionInterface
     **/
    public function search(
        AbstractQuery $query,
        $offset,
        $limit,
        ?ParamBag $params = null
    ) {
        // process EDS API communication tokens.
        $authenticationToken = $this->getAuthenticationToken();
        $sessionToken = $this->getSessionToken();
        $this->debug(
            "Authentication Token: $authenticationToken, SessionToken: $sessionToken"
        );

        // create query parameters from VuFind data
        $queryString = $query->getAllTerms();
        $paramsStr = implode('&', null !== $params ? $params->request() : []);
        $this->debug(
            "Query: $queryString, Limit: $limit, Offset: $offset, "
            . "Params: $paramsStr"
        );

        $baseParams = $this->getQueryBuilder()->build($query);
        $paramsStr = implode('&', $baseParams->request());
        $this->debug("BaseParams: $paramsStr ");
        if (null !== $params) {
            $baseParams->mergeWith($params);
        }
        $baseParams->set('resultsPerPage', $limit);
        $page = $limit > 0 ? floor($offset / $limit) + 1 : 1;
        $baseParams->set('pageNumber', $page);

        $searchModel = $this->paramBagToEBSCOSearchModel($baseParams);
        if (!$searchModel->isValid()) {
            // This may happen in the context of a blended search,
            // when the database is valid for another backend.
            return $this->createRecordCollection([]);
        }
        $qs = $searchModel->convertToQueryString();
        $this->debug("Search Model query string: $qs");
        try {
            $response = $this->client
                ->search($searchModel, $authenticationToken, $sessionToken);
        } catch (ApiException $e) {
            // if the auth or session token was invalid, try once more
            switch ($e->getApiErrorCode()) {
                case 104:
                case 108:
                case 109:
                    try {
                        // For error 104, retry auth token; for 108/9, retry sess
                        // token:
                        if ($e->getApiErrorCode() == 104) {
                            $authenticationToken
                                = $this->getAuthenticationToken(true);
                        } else {
                            $sessionToken = $this->getSessionToken(true);
                        }
                        $response = $this->client->search(
                            $searchModel,
                            $authenticationToken,
                            $sessionToken
                        );
                    } catch (Exception $e) {
                        throw new BackendException(
                            $e->getMessage(),
                            $e->getCode(),
                            $e
                        );
                    }
                    break;
                case 129:
                    // MSUL PC-1761 Avoid 129 errors being logged as critical errors
                    $errorMessage = "Unhandled EDS API error {$e->getApiErrorCode()} : {$e->getMessage()}";
                    $this->logWarning($errorMessage);
                    return $this->createRecordCollection([]);
                case 138:
                    // User requested unavailable deep search results; first extract
                    // the next legal position from the error message:
                    $parts
                        = explode(' ', trim($e->getApiDetailedErrorDescription()));
                    $legalPos = array_pop($parts);
                    // Now calculate the legal page number and throw an exception so
                    // the controller can fix it from here:
                    $legalPage = floor($legalPos / $limit);
                    throw new \VuFindSearch\Backend\Exception\DeepPagingException(
                        $e->getMessage(),
                        $e->getCode(),
                        $legalPage,
                        $e
                    );
                default:
                    $errorMessage = "Unhandled EDS API error {$e->getApiErrorCode()} : {$e->getMessage()}";
                    $this->logError($errorMessage);
                    throw new BackendException($errorMessage, $e->getCode(), $e);
            }
        } catch (Exception $e) {
            $this->debug('Exception found: ' . $e->getMessage());
            throw new BackendException($e->getMessage(), $e->getCode(), $e);
        }
        $collection = $this->createRecordCollection($response);
        $this->injectSourceIdentifier($collection);
        if ($this->isGuest && $collection instanceof RecordCollection) {
            $collection->setRestrictedView(true);
        }
        return $collection;
    }
}
