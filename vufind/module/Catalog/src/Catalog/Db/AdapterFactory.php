<?php

/**
 * Database utility class. May be used as a service or as a standard
 * Laminas factory.
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Db
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace Catalog\Db;

/**
 * Database utility class. May be used as a service or as a standard
 * Laminas factory.
 *
 * @category VuFind
 * @package  Db
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class AdapterFactory extends \VuFind\Db\AdapterFactory
{
    /**
     * Translate the connection string protocol into a driver name.
     * Customization: adds support for a mariadb connection string
     *
     * @param string $type Database type from connection string
     *
     * @return string
     */
    public function getDriverName($type)
    {
        switch (strtolower($type)) {
            case 'mysql':
                return 'mysqli';
            case 'mariadb':
                return 'mysqli';
            case 'oci8':
                return 'Oracle';
            case 'pgsql':
                return 'Pdo_Pgsql';
        }
        return $type;
    }
}
