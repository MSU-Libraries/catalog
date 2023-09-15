<?php

/**
 * ChangeTracker Test Class
 *
 * PHP version 7
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
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace CatalogTest\Db\Table;

use VuFind\Db\Table\ChangeTracker;

/**
 * ChangeTracker Test Class
 *
 * Class must be final due to use of "new static()" by LiveDatabaseTrait.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
final class ChangeTrackerTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\LiveDatabaseTrait;
    use \VuFindTest\Feature\LiveDetectionTrait;

    /**
     * Test change tracking
     *
     * @return void
     */
    public function testChangeTracker()
    {
        $core = 'testCore';
        $tracker = $this->getTable(ChangeTracker::class);
        $tracker->delete(['core' => $core]);

        // Create a new row:
        $tracker->index($core, 'test1', 1326833170);
        $row = $tracker->retrieve($core, 'test1');
        $this->assertNotNull($row);
        $this->assertNull($row->deleted);
        $this->assertEquals('2012-01-17 20:46:10', $row->last_record_change);
        $this->assertTrue(
            // use <= in case test runs too fast for values to become unequal:
            strtotime($row->first_indexed) <= strtotime($row->last_indexed)
        );

        // Try to index an earlier record version -- changes should be ignored:
        $tracker->index($core, 'test1', 1326830000);
        $row = $tracker->retrieve($core, 'test1');
        $this->assertNotNull($row);
        $this->assertNull($row->deleted);
        $this->assertEquals('2012-01-17 20:46:10', $row->last_record_change);
        $previousFirstIndexed = $row->first_indexed;
        $this->assertTrue(
            // use <= in case test runs too fast for values to become unequal:
            strtotime($row->first_indexed) <= strtotime($row->last_indexed)
        );

        // Sleep two seconds to be sure timestamps change:
        sleep(2);

        // Index a later record version -- this should lead to changes:
        $tracker->index($core, 'test1', 1326833176);
        $row = $tracker->retrieve($core, 'test1');
        $this->assertNotNull($row);
        $this->assertNull($row->deleted);
        $this->assertTrue(
            // use <= in case test runs too fast for values to become unequal:
            strtotime($row->first_indexed) <= strtotime($row->last_indexed)
        );
        $this->assertEquals('2012-01-17 20:46:16', $row->last_record_change);

        // Make sure the "first indexed" date hasn't changed!
        $this->assertEquals($previousFirstIndexed, $row->first_indexed);

        // Delete the record:
        $tracker->markDeleted($core, 'test1');
        $row = $tracker->retrieve($core, 'test1');
        $this->assertNotNull($row);
        $this->assertNotNull($row->deleted);

        // Delete a record that hasn't previously been encountered:
        $tracker->markDeleted($core, 'test2');
        $row = $tracker->retrieve($core, 'test2');
        $this->assertNotNull($row);
        $this->assertNotNull($row->deleted);

        // Index the previously-deleted record and make sure it undeletes properly:
        $tracker->index($core, 'test2', 1326833170);
        $row = $tracker->retrieve($core, 'test2');
        $this->assertNotNull($row);
        $this->assertNull($row->deleted);
        $this->assertEquals('2012-01-17 20:46:10', $row->last_record_change);

        // Clean up after ourselves:
        $tracker->delete(['core' => $core]);
    }
}
