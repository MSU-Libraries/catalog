<?php

/**
 * TODO - To remove after 10.1 - There is a fix for it - https://github.com/vufind-org/vufind/pull/4013
 * Hold Logic Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2007.
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
 * @package  ILS_Logic
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Catalog\ILS\Logic;

use VuFind\ILS\Logic\AvailabilityStatusInterface;

/**
 * Hold Logic Class
 *
 * @category VuFind
 * @package  ILS_Logic
 * @author   Robby ROUDON <roudonro@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Holds extends \VuFind\ILS\Logic\Holds
{
    /**
     * TODO - To remove after 10.1 - There is a fix for it - https://github.com/vufind-org/vufind/pull/4013
     * Get Hold Form
     *
     * Supplies holdLogic with the form details required to place a request
     *
     * @param array  $details  An array of item data
     * @param array  $HMACKeys An array of keys to hash
     * @param string $action   The action for which the details are built
     *
     * @return array             Details for generating URL
     */
    protected function getRequestDetails($details, $HMACKeys, $action)
    {
        if (
            ($details['availability'] ?? null) instanceof AvailabilityStatusInterface
            && empty($details['status'])
        ) {
            $details['status'] = $details['availability']->getStatusDescription();
        }
        return parent::getRequestDetails($details, $HMACKeys, $action);
    }
}
