<?php

/**
 * TODO remove after upgrade to 10.1.1 - Fix bug on user login
 * Row Definition for user
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
 * @package  Db_Row
 * @author   Robby ROUDON <roudonro@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace Catalog\Db\Row;

class User extends \VuFind\Db\Row\User
{
    /**
     * Get encrypted catalog password.
     *
     * @return ?string
     */
    public function getCatPassEnc(): ?string
    {
        return $this->cat_pass_enc ?? null;
    }
}

