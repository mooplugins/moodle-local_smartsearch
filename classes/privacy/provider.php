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
 * Privacy provider for Smart Search.
 *
 * @package    local_smartsearch
 * @copyright  2025 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_smartsearch\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\writer;

/**
 * Privacy Subsystem implementation for local_smartsearch.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Returns metadata about stored data.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_smartsearch_log',
            [
                'query' => 'privacy:metadata:local_smartsearch_log:query',
                'result_count' => 'privacy:metadata:local_smartsearch_log:result_count',
                'clicked_result_id' => 'privacy:metadata:local_smartsearch_log:clicked_result_id',
                'timestamp' => 'privacy:metadata:local_smartsearch_log:timestamp',
            ],
            'privacy:metadata:local_smartsearch_log'
        );

        $collection->add_database_table(
            'local_smartsearch_index',
            [
                'recordtype' => 'privacy:metadata:local_smartsearch_index:recordtype',
                'recordid' => 'privacy:metadata:local_smartsearch_index:recordid',
                'title' => 'privacy:metadata:local_smartsearch_index:title',
                'subtitle' => 'privacy:metadata:local_smartsearch_index:subtitle',
                'keywords' => 'privacy:metadata:local_smartsearch_index:keywords',
                'metadata' => 'privacy:metadata:local_smartsearch_index:metadata',
                'updatedat' => 'privacy:metadata:local_smartsearch_index:updatedat',
            ],
            'privacy:metadata:local_smartsearch_index'
        );

        return $collection;
    }

    /**
     * Get contexts containing user data for the specified user.
     *
     * @param int $userid The user ID.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();

        if (
            $DB->record_exists('local_smartsearch_index', [
            'recordtype' => 'user',
            'recordid' => $userid,
            ])
        ) {
            $contextlist->add_user_context($userid);
        }

        return $contextlist;
    }

    /**
     * Export user data for approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        $records = $DB->get_records('local_smartsearch_index', [
            'recordtype' => 'user',
            'recordid' => $userid,
        ]);

        if (empty($records)) {
            return;
        }

        $context = \context_user::instance($userid);
        foreach ($records as $record) {
            $data = (object) [
                'title' => $record->title,
                'subtitle' => $record->subtitle,
                'keywords' => $record->keywords,
                'updatedat' => transform::datetime($record->updatedat),
            ];
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_smartsearch')],
                $data
            );
        }
    }

    /**
     * Delete all user data in the given context.
     *
     * @param \context $context The context.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if ($context->contextlevel !== CONTEXT_USER) {
            return;
        }

        $DB->delete_records('local_smartsearch_index', [
            'recordtype' => 'user',
            'recordid' => $context->instanceid,
        ]);
    }

    /**
     * Delete user data for approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        self::delete_data_for_all_users_in_context(\context_user::instance($contextlist->get_user()->id));
    }
}
