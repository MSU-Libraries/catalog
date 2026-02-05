<?php

/**
 * MSUL PC-1307
 * Class encapsulating publication details.
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
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */

namespace Catalog\RecordDriver\Response;

/**
 * Class encapsulating publication details.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
class PublicationDetails extends \VuFind\RecordDriver\Response\PublicationDetails
{
    /**
     * Place of publication
     *
     * @var string
     */
    protected $place_link;

    /**
     * Name of publisher
     *
     * @var string
     */
    protected $name_link;

    /**
     * Date of publication
     *
     * @var string
     */
    protected $date_link;

    /**
     * Constructor
     *
     * @param string $place      Place of publication
     * @param string $name       Name of publisher
     * @param string $date       Date of publication
     * @param string $place_link Place of publication linked value
     * @param string $name_link  Name of publisher linked value
     * @param string $date_link  Date of publication linked value
     */
    public function __construct($place, $name, $date, $place_link, $name_link, $date_link)
    {
        $this->place = $place;
        $this->name = $name;
        $this->date = $date;
        $this->place_link = $place_link;
        $this->name_link = $name_link;
        $this->date_link = $date_link;
    }

    /**
     * Get place of publication link
     *
     * @return string
     */
    public function getPlaceLinked()
    {
        return $this->place_link;
    }

    /**
     * Get name of publisher link
     *
     * @return string
     */
    public function getNameLinked()
    {
        return $this->name_link;
    }

    /**
     * Get date of publication link
     *
     * @return string
     */
    public function getDateLinked()
    {
        return $this->date_link;
    }

    /**
     * Represent object as a string
     *
     * @return string
     */
    public function __toString()
    {
        return trim(
            preg_replace(
                '/\s+/',
                ' ',
                implode(
                    ' ',
                    [
                        $this->place,
                        $this->name,
                        $this->date,
                        $this->place_link,
                        $this->name_link,
                        $this->date_link,
                    ]
                )
            )
        );
    }
}
