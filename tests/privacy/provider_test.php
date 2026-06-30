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
 * Unit tests for privacy provider.
 *
 * @package    local_smartsearch
 * @copyright  2025 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_smartsearch\privacy;

/**
 * Privacy provider tests.
 *
 * @covers \local_smartsearch\privacy\provider
 */
final class provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * Test metadata collection.
     */
    public function test_get_metadata(): void {
        $collection = new \core_privacy\local\metadata\collection('local_smartsearch');
        $collection = provider::get_metadata($collection);
        $this->assertNotEmpty($collection->get_collection());
    }

    /**
     * Test user contexts are returned when a user is indexed.
     */
    public function test_get_contexts_for_userid(): void {
        global $DB;

        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $DB->insert_record('local_smartsearch_index', (object) [
            'recordtype' => 'user',
            'recordid' => $user->id,
            'title' => fullname($user),
            'url' => '/user/profile.php?id=' . $user->id,
            'updatedat' => time(),
        ]);

        $contextlist = provider::get_contexts_for_userid($user->id);
        $this->assertCount(1, $contextlist);
        $this->assertEquals(\context_user::instance($user->id)->id, $contextlist->current()->id);
    }

    /**
     * Test deleting indexed user data for a user context.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;

        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $DB->insert_record('local_smartsearch_index', (object) [
            'recordtype' => 'user',
            'recordid' => $user->id,
            'title' => fullname($user),
            'url' => '/user/profile.php?id=' . $user->id,
            'updatedat' => time(),
        ]);

        provider::delete_data_for_all_users_in_context(\context_user::instance($user->id));
        $this->assertFalse($DB->record_exists('local_smartsearch_index', [
            'recordtype' => 'user',
            'recordid' => $user->id,
        ]));
    }
}
