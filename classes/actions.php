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
 * Actions class for Smart Search.
 *
 * @package    local_smartsearch
 * @copyright  2025 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_smartsearch;


/**
 * Handles quick actions for search results.
 */
class actions {
    /**
     * Get available actions for a user result.
     *
     * @param int $userid The user ID
     * @param int $targetuserid The target user ID
     * @return array<int, array{label: string, url: \moodle_url, icon: string}> Array of action arrays
     */
    public static function get_user_actions(int $userid, int $targetuserid): array {
        global $CFG;
        $actions = [];

        // View profile.
        if (permissions::can_view_user($userid, $targetuserid)) {
            $actions[] = [
                'label' => get_string('action_viewprofile', 'local_smartsearch'),
                'url' => new \moodle_url('/user/profile.php', ['id' => $targetuserid]),
                'icon' => 'user',
            ];
        }

        // Send message.
        if ($userid != $targetuserid && !empty($CFG->messaging)) {
            $actions[] = [
                'label' => get_string('action_sendmessage', 'local_smartsearch'),
                'url' => new \moodle_url('/message/index.php', ['id' => $targetuserid]),
                'icon' => 'message',
            ];
        }

        // Login as (admins only).
        if (has_capability('moodle/user:loginas', \context_system::instance(), $userid)) {
            $actions[] = [
                'label' => get_string('action_loginas', 'local_smartsearch'),
                'url' => new \moodle_url('/course/loginas.php', ['id' => $targetuserid, 'sesskey' => sesskey()]),
                'icon' => 'login',
            ];
        }

        // Edit user (admins only).
        if (has_capability('moodle/user:edit', \context_user::instance($targetuserid), $userid)) {
            $actions[] = [
                'label' => get_string('action_edit', 'local_smartsearch'),
                'url' => new \moodle_url('/user/edit.php', ['id' => $targetuserid]),
                'icon' => 'edit',
            ];
        }

        return $actions;
    }

    /**
     * Get available actions for a course result.
     *
     * @param int $userid The user ID
     * @param int $courseid The course ID
     * @return array<int, array{label: string, url: \moodle_url, icon: string}> Array of action arrays
     */
    public static function get_course_actions(int $userid, int $courseid): array {
        $actions = [];

        // Open course.
        if (permissions::can_view_course($userid, $courseid)) {
            $actions[] = [
                'label' => get_string('action_view', 'local_smartsearch'),
                'url' => new \moodle_url('/course/view.php', ['id' => $courseid]),
                'icon' => 'graduation-cap',
            ];
        }

        // Edit settings (teachers/admins).
        if (has_capability('moodle/course:update', \context_course::instance($courseid), $userid)) {
            $actions[] = [
                'label' => get_string('action_edit', 'local_smartsearch'),
                'url' => new \moodle_url('/course/edit.php', ['id' => $courseid]),
                'icon' => 'edit',
            ];
        }

        // View participants.
        if (has_capability('moodle/course:viewparticipants', \context_course::instance($courseid), $userid)) {
            $actions[] = [
                'label' => get_string('participants', 'local_smartsearch'),
                'url' => new \moodle_url('/user/index.php', ['id' => $courseid]),
                'icon' => 'users',
            ];
        }

        return $actions;
    }

    /**
     * Get available actions for an activity result.
     *
     * @param int $userid The user ID
     * @param int $cmid The course module ID
     * @return array<int, array{label: string, url: \moodle_url, icon: string}> Array of action arrays
     */
    public static function get_activity_actions(int $userid, int $cmid): array {
        $cminfo = permissions::get_cm_info_for_user($userid, $cmid);
        if (!$cminfo) {
            return [];
        }

        $actions = [];

        $actions[] = [
            'label' => get_string('action_view', 'local_smartsearch'),
            'url' => $cminfo->url ?? new \moodle_url('/course/view.php', ['id' => $cminfo->course]),
            'icon' => 'puzzle-piece',
        ];

        if (has_capability('mod/' . $cminfo->modname . ':manage', $cminfo->context, $userid)) {
            $actions[] = [
                'label' => get_string('action_edit', 'local_smartsearch'),
                'url' => new \moodle_url('/course/modedit.php', ['update' => $cmid]),
                'icon' => 'edit',
            ];
        }

        return $actions;
    }
}
