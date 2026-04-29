<?php

/**
 * Authentication view helper
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  View_Helpers
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Catalog\View\Helper\Root;

use Laminas\Config\Config;
use Laminas\Config\Reader\Ini as IniReader;
use VuFind\Config\PathResolver;

use function array_key_exists;
use function count;

/**
 * Authentication handler to add additional data to the view
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Auth extends \VuFind\View\Helper\Root\Auth
{
    /**
     * Constructor
     *
     * @param \VuFind\Auth\Manager          $manager          Authentication manager
     * @param \VuFind\Auth\ILSAuthenticator $ilsAuthenticator ILS Authenticator
     * @param ?PathResolver                 $pathResolver     MSUL - Config file path resolver
     */
    public function __construct(
        \VuFind\Auth\Manager $manager,
        \VuFind\Auth\ILSAuthenticator $ilsAuthenticator,
        protected ?PathResolver $pathResolver = null // MSUL
    ) {
        parent::__construct($manager, $ilsAuthenticator);
    }

    /**
     * Determines if the given patron is a community borrower based
     * on the username field from their patron data.
     *
     * @return bool
     */
    public function isCommunityBorrower()
    {
        $isCommunityBorrower = false;
        $patron = $this->getILSPatron();

        if ($patron != null && array_key_exists('username', $patron)) {
            // If the username is a 9-digit string (barcode) then they are a community borrower
            if (preg_match('/[0-9]{9}/', $patron['username'])) {
                $isCommunityBorrower = true;
            }
        }

        return $isCommunityBorrower;
    }

    /**
     * Determines if the current request is comming from on campus
     * based on the client IP as compared to the configured IP ranges in
     * the permissions.ini for the EDS module.
     *
     * @return bool
     */
    public function isOnCampus()
    {
        $isOnCampus = false;
        $ranges = [];
        $ip = ip2long($_SERVER['REMOTE_ADDR']);

        $fullpath = $this->pathResolver->getConfigPath('permissions.ini', 'config/vufind');
        $config = new Config((new IniReader())->fromFile($fullpath, true));

        if (
            $config['default']['EDSModule'] != null &&
            $config['default']['EDSModule']['ipRange'] != null
        ) {
            $ranges = $config['default']['EDSModule']['ipRange'];
            foreach ($ranges as $range) {
                $ipParts = explode('-', $range);
                $lowIp = count($ipParts) > 0 ? ip2long($ipParts[0]) : '';
                $highIp = count($ipParts) > 1 ? ip2long($ipParts[1]) : '';
                if ($ip <= $highIp && $lowIp <= $ip) {
                    $isOnCampus = true;
                    break;
                }
            }
        }

        return $isOnCampus;
    }
}
