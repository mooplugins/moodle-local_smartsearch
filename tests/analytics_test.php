<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Unit tests for analytics class.
 *
 * @package    local_smartsearch
 * @copyright  2025 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_smartsearch;

/**
 * Analytics tests.
 *
 * @covers \local_smartsearch\analytics
 */
final class analytics_test extends \advanced_testcase {
    /**
     * Test old analytics records are deleted according to retention.
     */
    public function test_cleanup_old_data(): void {
        global $DB;

        $this->resetAfterTest();

        set_config('enable_analytics', 1, 'local_smartsearch');
        set_config('analytics_retention', 30, 'local_smartsearch');

        $oldtime = time() - (40 * DAYSECS);
        $newtime = time() - (5 * DAYSECS);

        $DB->insert_record('local_smartsearch_log', (object) [
            'query' => 'old query',
            'result_count' => 1,
            'timestamp' => $oldtime,
        ]);
        $DB->insert_record('local_smartsearch_log', (object) [
            'query' => 'new query',
            'result_count' => 2,
            'timestamp' => $newtime,
        ]);

        $deleted = analytics::cleanup_old_data();
        $this->assertEquals(1, $deleted);
        $this->assertEquals(1, $DB->count_records('local_smartsearch_log'));
        $this->assertTrue($DB->record_exists('local_smartsearch_log', ['query' => 'new query']));
    }
}
