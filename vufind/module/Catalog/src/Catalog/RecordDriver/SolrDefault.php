<?php

/**
 * Default model for Solr records -- used when a more specific model based on
 * the record_format field cannot be found.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010, 2022.
 * Copyright (C) The National Library of Finland 2019.
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
 * @package  Record_Drivers
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */

namespace Catalog\RecordDriver;

/**
 * Default model for Solr records -- used when a more specific model based on
 * the record_format field cannot be found.
 *
 * This should be used as the base class for all Solr-based record models.
 *
 * @category VuFind
 * @package  Record_Drivers
 * @author   Megan Schanz <schanzme@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
class SolrDefault extends \VuFind\RecordDriver\SolrDefault
{
    /**
     * Get electronic journal links
     *
     * @return array
     */
    public function geteJournalLinks()
    {
        return [];
    }

    /**
     * Get publisher
     *
     * @return array
     */
    public function getPublisher()
    {
        return [];
    }

    /**
     * Get notes
     *
     * @return array
     */
    public function getNotes()
    {
        return [];
    }

    /**
     * Get the numbering peculiarity notes
     *
     * @return array Note fields from Solr
     */
    public function getNumberingPeculiaritiesNotes()
    {
        return [];
    }

    /**
     * Get phyiscal description
     *
     * @return array
     */
    public function getPhysical()
    {
        return [];
    }

    /**
     * Get genres
     *
     * @return array
     */
    public function getGenres()
    {
        return [];
    }

    /**
     * Get Sierra bib number
     *
     * @return array
     */
    public function getSierraBN()
    {
        return '';
    }

    /**
     * Get locations
     *
     * @return array
     */
    public function getLocations()
    {
        return [];
    }

    /**
     * Get location
     *
     * @return array
     */
    public function getLocation()
    {
        return [];
    }

    /**
     * Get breadcrumb
     *
     * @return array
     */
    public function getBreadcrumb()
    {
        return '';
    }

    /**
     * Get universally unique ID
     *
     * @return array
     */
    public function getUUID()
    {
        return [];
    }

    /**
     * Get full call number
     *
     * @return array
     */
    public function getCallNumbers()
    {
        return [];
    }

    /**
     * Get cartographic data
     *
     * @return array
     */
    public function getCartographicData()
    {
        return [];
    }

    /**
     * Get ISBN data with type of ISBN ('valid', 'canceled/invalid')
     *
     * @return array An array of arrays, subarrays containing 'isn' and 'type'
     */
    public function getISBNsWithType()
    {
        return [];
    }

    /**
     * Get ISSN data with type of ISSN ('valid', 'incorrect', or 'canceled')
     *
     * @return array An array of arrays, subarrays containing 'isn' and 'type'
     */
    public function getISSNsWithType()
    {
        return [];
    }

    /**
     * Get summary
     *
     * @return array
     */
    public function getSummary()
    {
        return [];
    }

    /**
     * Get the bookplate data
     *
     * @return array
     */
    public function getLocalNotes()
    {
        return [];
    }

    /**
     * Get the scale notes
     *
     * @return array
     */
    public function getScaleNotes()
    {
        return [];
    }

    /**
     * Get the cite as notes
     *
     * @return array
     */
    public function getCiteAsNotes()
    {
        return [];
    }

    /**
     * Get the video game platform
     *
     * @return array
     */
    public function getPlatform()
    {
        return [];
    }

    /**
     * Get the translated from languages
     *
     * @return array Content from Solr
     */
    public function getLanguageOriginal()
    {
        return [];
    }

    /**
     * Get the dissertation note
     *
     * @return array Content from Solr
     */
    public function getDissertationNotes()
    {
        return [];
    }
}
