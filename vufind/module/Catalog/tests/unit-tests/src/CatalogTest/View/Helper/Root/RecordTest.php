<?php

/**
 * Record Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Michigan State University 2024.
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
 * @package  Tests
 * @author   Nathan Collins <colli372@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace CatalogTest\View\Helper\Root;

use Catalog\View\Helper\Root\Record;

/**
 * Record Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Nathan Collins <colli372@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class RecordTest extends \PHPUnit\Framework\TestCase
{

    /**
     * Test dedupliateLocationStrings.
     *
     * @return void
     */
    public function testDeduplicateLocationStrings()
    {
        # Exact duplicates
        $this->assertEquals(
            "MSU Main Library",
            Record::deduplicateLocationStrings(
                "MSU Main Library",
                "MSU Main Library"
            )
        );
        # Prefix duplication
        $this->assertEquals(
            "MSU Remote Storage - Dissertations & Theses",
            Record::deduplicateLocationStrings(
                "MSU Remote Storage",
                "MSU Dissertations & Theses",
            )
        );
        # Whitespace and casing difference within second location string
        $this->assertEquals(
            "MSU G.M. Kline Digital/Media - Video Game Collection, 4 West",
            Record::deduplicateLocationStrings(
                "MSU G.M. Kline Digital/Media",
                "MSU G.M.KLine DIgital/Media - Video Game Collection, 4 West"
            )
        );
    }
}
