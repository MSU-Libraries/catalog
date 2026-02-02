<?php

/**
 * Multiple Backend Driver.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2012-2021.
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
 * @package  ILSdrivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */

namespace Catalog\ILS\Driver;

use VuFind\Exception\ILS as ILSException;
use VuFind\ILS\Driver\PluginManager;

use function in_array;
use function is_array;
use function strlen;

/**
 * Multiple Backend Driver.
 *
 * This driver allows to use multiple backends determined by a record id or
 * user id prefix (e.g. source.12345).
 *
 * @category VuFind
 * @package  ILSdrivers
 * @author   Nathan Collins <colli372@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class MultiBackend extends \VuFind\ILS\Driver\MultiBackend
{
    use \VuFind\Log\LoggerAwareTrait {
        logError as error;
    }

    /**
     * ID fields in holds
     */
    public const HOLD_ID_FIELDS = ['id', 'item_id', 'cat_username'];

    /**
     * The default driver to use
     *
     * @var string
     */
    protected $defaultDriver;

    /**
     * An array of methods that should determine source from a specific parameter
     * field
     *
     * @var array
     */
    protected $sourceCheckFields = [
        'cancelHolds' => 'cat_username',
        'cancelILLRequests' => 'cat_username',
        'cancelStorageRetrievalRequests' => 'cat_username',
        'changePassword' => 'cat_username',
        'getCancelHoldDetails' => 'cat_username',
        'getCancelILLRequestDetails' => 'cat_username',
        'getCancelStorageRetrievalRequestDetails' => 'cat_username',
        'getMyFines' => 'cat_username',
        'getMyProfile' => 'cat_username',
        'getMyTransactionHistory' => 'cat_username',
        'getMyTransactions' => 'cat_username',
        'renewMyItems' => 'cat_username',
    ];

    /**
     * Methods that don't have parameters that allow the correct source to be
     * determined. These methods are only supported for the default driver.
     */
    protected $methodsWithNoSourceSpecificParameters = [
        'findReserves',
        'getCourses',
        'getDepartments',
        'getFunds',
        'getInstanceByBibId', // MSU
        'getInstructors',
        'getNewItems',
        'getOfflineMode',
        'getSuppressedAuthorityRecords',
        'getSuppressedRecords',
        'loginIsHidden',
    ];

    /**
     * Constructor
     *
     * @param \VuFind\Config\ConfigManagerInterface $configManager Configuration manager
     * @param \VuFind\Auth\ILSAuthenticator         $ilsAuth       ILS authenticator
     * @param PluginManager                         $driverManager ILS driver manager
     */
    public function __construct(
        \VuFind\Config\ConfigManagerInterface $configManager,
        protected \VuFind\Auth\ILSAuthenticator $ilsAuth,
        PluginManager $driverManager
    ) {
        parent::__construct($configManager, $ilsAuth, $driverManager);
        $this->ilsAuth = $ilsAuth;
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
     * @return mixed An array of associative arrays representing reserve items
     */
    public function findReserves($course, $inst, $dept)
    {
        if ($driver = $this->getDriver($this->defaultDriver)) {
            // MSU - Get all the reserves from the default driver
            return $driver->findReserves($course, $inst, $dept);
        }
        throw new ILSException('No suitable backend driver found');
    }

    /**
     * Get Pick Up Locations
     *
     * This is responsible get a list of valid library locations for holds / recall
     * retrieval
     *
     * @param array $patron      Patron information returned by the patronLogin
     * method.
     * @param array $holdDetails Optional array, only passed in when getting a list
     * in the context of placing or editing a hold. When placing a hold, it contains
     * most of the same values passed to placeHold, minus the patron data. When
     * editing a hold it contains all the hold information returned by getMyHolds.
     * May be used to limit the pickup options or may be ignored. The driver must
     * not add new options to the return array based on this data or other areas of
     * VuFind may behave incorrectly.
     *
     * @return array        An array of associative arrays with locationID and
     * locationDisplay keys
     */
    public function getPickUpLocations($patron = false, $holdDetails = null)
    {
        // MSU - If MultiBackend without MultiILS auth, cat_username source will be empty
        $source = $this->getSource($patron['cat_username']) ?:
            $this->getSource($holdDetails['id'] ?? $holdDetails['item_id'] ?? '');
        if ($driver = $this->getDriver($source)) {
            if ($id = ($holdDetails['id'] ?? $holdDetails['item_id'] ?? '')) {
                if (!$this->driverSupportsSource($source, $id)) {
                    // Return empty array since the sources don't match
                    return [];
                }
            }
            $locations = $driver->getPickUpLocations(
                $this->stripIdPrefixes($patron, $source),
                $this->stripIdPrefixes(
                    $holdDetails,
                    $source,
                    self::HOLD_ID_FIELDS
                )
            );
            return $this->addIdPrefixes($locations, $source);
        }
        throw new ILSException('No suitable backend driver found');
    }

    /**
     * Place Hold
     *
     * Attempts to place a hold or recall on a particular item and returns
     * an array with result details
     *
     * @param array $holdDetails An array of item and patron data
     *
     * @return mixed An array of data on the request including
     * whether or not it was successful and a system message (if available)
     */
    public function placeHold($holdDetails)
    {
        // MSU - If MultiBackend without MultiILS auth, cat_username source will be empty
        $source = $this->getSource($holdDetails['patron']['cat_username']) ?:
            $this->getSource($holdDetails['id'] ?? $holdDetails['item_id'] ?? '');
        if ($driver = $this->getDriver($source)) {
            if (!$this->driverSupportsSource($source, $holdDetails['id'])) {
                return [
                    'success' => false,
                    'sysMessage' => 'ILSMessages::hold_wrong_user_institution',
                ];
            }
            $holdDetails = $this->stripIdPrefixes($holdDetails, $source);
            return $driver->placeHold($holdDetails);
        }
        throw new ILSException('No suitable backend driver found');
    }

    /**
     * Extract source from the given ID
     *
     * @param string $id The id to be split
     *
     * @return string Source
     */
    protected function getSource($id)
    {
        $pos = strpos($id, '.');
        if ($pos > 0) {
            return substr($id, 0, $pos);
        }

        // MSUL - When no prefix found in id, use the default source
        if (!$pos && $this->defaultDriver) {
            return $this->defaultDriver;
        }

        return '';
    }

    /**
     * Change local ID's to global ID's in the given array
     *
     * @param mixed  $data         The data to be modified, normally
     * array or array of arrays
     * @param string $source       Source code
     * @param array  $modifyFields Fields to be modified in the array
     *
     * @return mixed     Modified array or empty/null if that input was
     *                   empty/null
     */
    protected function addIdPrefixes(
        $data,
        $source,
        $modifyFields = ['id', 'cat_username']
    ) {
        if (empty($source) || empty($data) || !is_array($data)) {
            return $data;
        }

        foreach ($data as $key => $value) {
            // MSU Code removed
            if (is_array($value)) {
                $data[$key] = $this->addIdPrefixes(
                    $value,
                    $source,
                    $modifyFields
                );
            } else {
                if (
                    !ctype_digit((string)$key)
                    && $value !== ''
                    && in_array($key, $modifyFields)
                ) {
                    $data[$key] = "$source.$value";
                }
            }
        }
        return $data;
    }

    /**
     * Change global ID's to local ID's in the given array
     *
     * @param mixed  $data         The data to be modified, normally
     * array or array of arrays
     * @param string $source       Source code
     * @param array  $modifyFields Fields to be modified in the array
     * @param array  $ignoreFields Fields to be ignored during recursive processing
     *
     * @return mixed     Modified array or empty/null if that input was
     *                   empty/null
     */
    protected function stripIdPrefixes(
        $data,
        $source,
        $modifyFields = ['id', 'cat_username'],
        $ignoreFields = []
    ) {
        if (!isset($data) || empty($data)) {
            return $data;
        }
        $array = is_array($data) ? $data : [$data];

        foreach ($array as $key => $value) {
            // MSU Code removed
            if (is_array($value)) {
                if (in_array($key, $ignoreFields)) {
                    continue;
                }
                $array[$key] = $this->stripIdPrefixes(
                    $value,
                    $source,
                    $modifyFields
                );
            } else {
                $prefixLen = strlen($source) + 1;
                if (
                    (!is_array($data)
                    || (!ctype_digit((string)$key) && in_array($key, $modifyFields)))
                    && strncmp("$source.", $value, $prefixLen) == 0
                ) {
                    $array[$key] = substr($value, $prefixLen);
                }
            }
        }
        return is_array($data) ? $array : $array[0];
    }
}
