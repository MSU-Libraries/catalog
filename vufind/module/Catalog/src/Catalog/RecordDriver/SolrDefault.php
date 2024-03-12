<?php

/**
 * Default values for the record
 *
 * PHP version 7
 *
 * @category VuFind
 * @package  Record_Drivers
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */

namespace Catalog\RecordDriver;

/**
 * Populates data about the record. Used when no more specific record
 * driver is found.
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
    public function getFullCallNumber()
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
    public function getTranslatedFrom()
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
