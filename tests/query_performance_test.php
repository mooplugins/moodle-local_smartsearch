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
 * Query performance tests for Smart Search.
 *
 * @package    local_smartsearch
 * @copyright  2025 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_smartsearch;

/**
 * Ensures search does not scale DB reads linearly with every result (N+1 guard).
 *
 * @covers \local_smartsearch\query
 */
final class query_performance_test extends \advanced_testcase {
    /**
     * Per-result read budget above the single-area SQL fetch and permission caches.
     */
    private const MAX_READS_PER_RESULT = 12;

    /**
     * Search with many course hits must not trigger linear N+1 DB reads.
     */
    public function test_search_read_count_bounded(): void {
        global $DB;

        $this->resetAfterTest();

        set_config('enable', 1, 'local_smartsearch');
        set_config('enable_analytics', 0, 'local_smartsearch');
        set_config('enable_users', 0, 'local_smartsearch');
        set_config('enable_courses', 1, 'local_smartsearch');
        set_config('enable_activities', 0, 'local_smartsearch');
        set_config('enable_settings', 0, 'local_smartsearch');
        set_config('enable_plugins', 0, 'local_smartsearch');
        set_config('enable_categories', 0, 'local_smartsearch');

        $admin = get_admin();
        $this->setUser($admin);

        $needle = 'alphexperf';
        $resultcount = 20;

        for ($i = 0; $i < $resultcount; $i++) {
            $this->getDataGenerator()->create_course([
                'fullname' => "Course {$i} {$needle}",
                'shortname' => "c{$i}{$needle}",
            ]);
        }

        $readsbefore = $DB->perf_get_reads();
        $results = query::search($needle, (int) $admin->id, 50);
        $readsused = $DB->perf_get_reads() - $readsbefore;

        $this->assertArrayHasKey('course', $results);
        $this->assertCount($resultcount, $results['course']);

        $maxreads = 40 + ($resultcount * self::MAX_READS_PER_RESULT);
        $this->assertLessThan(
            $maxreads,
            $readsused,
            "Search used {$readsused} DB reads for {$resultcount} results; possible N+1 (limit {$maxreads})."
        );
    }

    /**
     * Activity search with many hits in one course must reuse modinfo (not N+1 per result).
     */
    public function test_search_activity_read_count_bounded(): void {
        global $DB;

        $this->resetAfterTest();

        set_config('enable', 1, 'local_smartsearch');
        set_config('enable_analytics', 0, 'local_smartsearch');
        set_config('enable_users', 0, 'local_smartsearch');
        set_config('enable_courses', 0, 'local_smartsearch');
        set_config('enable_activities', 1, 'local_smartsearch');
        set_config('enable_settings', 0, 'local_smartsearch');
        set_config('enable_plugins', 0, 'local_smartsearch');
        set_config('enable_categories', 0, 'local_smartsearch');

        $admin = get_admin();
        $this->setUser($admin);

        $needle = 'actperfxy';
        $resultcount = 20;

        $course = $this->getDataGenerator()->create_course();
        $pagegen = $this->getDataGenerator()->get_plugin_generator('mod_page');
        for ($i = 0; $i < $resultcount; $i++) {
            $pagegen->create_instance([
                'course' => $course->id,
                'name' => "Page {$i} {$needle}",
            ]);
        }

        // Observers index one module at a time; rebuild the full activity index after bulk create.
        indexer::index_all(0);

        $readsbefore = $DB->perf_get_reads();
        $results = query::search($needle, (int) $admin->id, 50);
        $readsused = $DB->perf_get_reads() - $readsbefore;

        $this->assertArrayHasKey('activity', $results);
        $this->assertCount($resultcount, $results['activity']);

        $maxreads = 40 + ($resultcount * self::MAX_READS_PER_RESULT);
        $this->assertLessThan(
            $maxreads,
            $readsused,
            "Activity search used {$readsused} DB reads for {$resultcount} results; possible N+1 (limit {$maxreads})."
        );
    }
}
