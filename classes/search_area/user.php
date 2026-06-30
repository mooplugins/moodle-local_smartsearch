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
 * User search area.
 *
 * @package    local_smartsearch
 * @copyright  2025 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_smartsearch\search_area;


/**
 * User search area implementation.
 */
class user extends base {
    /** @var bool */
    private static $bulkindexactive = false;

    /** @var array<int, string[]> */
    private static $bulkroles = [];

    /** @var array<int, int> */
    private static $bulkenrolcounts = [];

    /**
     * Preload role and enrolment metadata for bulk user indexing (two queries total).
     */
    public static function begin_bulk_index(): void {
        global $DB;

        self::$bulkindexactive = true;
        self::$bulkroles = [];
        self::$bulkenrolcounts = [];

        $roles = $DB->get_records_sql(
            "SELECT ra.userid, r.shortname
               FROM {role_assignments} ra
               JOIN {role} r ON r.id = ra.roleid"
        );
        foreach ($roles as $role) {
            $userid = (int) $role->userid;
            if (!isset(self::$bulkroles[$userid])) {
                self::$bulkroles[$userid] = [];
            }
            if (!in_array($role->shortname, self::$bulkroles[$userid], true)) {
                self::$bulkroles[$userid][] = $role->shortname;
            }
        }

        $counts = $DB->get_records_sql(
            "SELECT ue.userid, COUNT(DISTINCT e.courseid) AS enrolcount
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.status = 0
                AND ue.status = 0
           GROUP BY ue.userid"
        );
        foreach ($counts as $count) {
            self::$bulkenrolcounts[(int) $count->userid] = (int) $count->enrolcount;
        }
    }

    /**
     * Clear bulk index metadata after a user indexing run.
     */
    public static function end_bulk_index(): void {
        self::$bulkindexactive = false;
        self::$bulkroles = [];
        self::$bulkenrolcounts = [];
    }
    /**
     * Get the display name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('category_users', 'local_smartsearch');
    }

    /**
     * Get the recordset of users to index.
     *
     * @param int $modifiedfrom Timestamp
     * @return \moodle_recordset|null
     */
    public function get_recordset(int $modifiedfrom = 0): ?\moodle_recordset {
        global $DB;

        $params = [];
        $where = 'deleted = 0';

        if ($modifiedfrom > 0) {
            $where .= ' AND (timecreated > ? OR timemodified > ?)';
            $params[] = $modifiedfrom;
            $params[] = $modifiedfrom;
        }

        return $DB->get_recordset_select('user', $where, $params, 'id ASC', 'id, firstname, lastname, email, username, suspended');
    }

    /**
     * Index a user.
     *
     * @param int $itemid User ID
     * @param \stdClass|null $sourcerecord Optional source row from get_recordset()
     * @return array|null
     */
    public function index_item(int $itemid, ?\stdClass $sourcerecord = null): ?array {
        global $DB, $CFG;

        $user = $sourcerecord ?? $DB->get_record('user', ['id' => $itemid, 'deleted' => 0]);
        if (!$user) {
            return null;
        }

        $fullname = fullname($user);
        $keywords = [$user->username];

        // Add email if searchable.
        if (get_config('local_smartsearch', 'search_user_emails')) {
            $keywords[] = $user->email;
        }

        // Get user roles and enrollments for context.
        $roles = $this->get_user_roles($itemid);
        $enrolcount = $this->get_user_enrolment_count($itemid);

        $subtitle = '';
        if (!empty($roles)) {
            $subtitle .= get_string('roles', 'local_smartsearch') . ': ' . implode(', ', $roles);
        }
        if ($enrolcount > 0) {
            if ($subtitle) {
                $subtitle .= ' | ';
            }
            // Use the translated string directly - it should be stored as-is in the database.
            $enrolledstr = get_string('enrolledincourses', 'local_smartsearch');
            $subtitle .= $enrolledstr . ': ' . $enrolcount;
        }

        $profileurl = new \moodle_url('/user/profile.php', ['id' => $itemid]);

        return [
            'recordtype' => 'user',
            'recordid' => $itemid,
            'contextpath' => '',
            'title' => $fullname,
            'subtitle' => $subtitle,
            'keywords' => $keywords,
            'url' => $profileurl->out(false),
            'metadata' => [
                'firstname' => $user->firstname,
                'lastname' => $user->lastname,
                'email' => $user->email,
                'username' => $user->username,
                'suspended' => (bool) $user->suspended,
            ],
        ];
    }

    /**
     * Get user URL.
     *
     * @param int $itemid User ID
     * @return \moodle_url|null
     */
    public function get_item_url(int $itemid): ?\moodle_url {
        return new \moodle_url('/user/profile.php', ['id' => $itemid]);
    }

    /**
     * Get user actions.
     *
     * @param int $itemid User ID
     * @param int $userid Current user ID
     * @return array
     */
    public function get_item_actions(int $itemid, int $userid): array {
        return \local_smartsearch\actions::get_user_actions($userid, $itemid);
    }

    /**
     * Check if user can be indexed/viewed.
     *
     * @param int $userid Current user ID
     * @param int $itemid Target user ID
     * @return bool
     */
    public function can_index(int $userid, int $itemid): bool {
        return \local_smartsearch\permissions::can_view_user($userid, $itemid);
    }

    /**
     * Get indexing frequency.
     *
     * @return string
     */
    public function get_indexing_frequency(): string {
        return 'realtime';
    }

    /**
     * Get record type.
     *
     * @return string
     */
    public function get_record_type(): string {
        return 'user';
    }

    /**
     * Get user roles.
     *
     * @param int $userid User ID
     * @return array
     */
    protected function get_user_roles(int $userid): array {
        if (self::$bulkindexactive) {
            return self::$bulkroles[$userid] ?? [];
        }

        global $DB;
        $roles = $DB->get_records_sql(
            "SELECT DISTINCT r.shortname
             FROM {role} r
             JOIN {role_assignments} ra ON ra.roleid = r.id
             WHERE ra.userid = ?",
            [$userid]
        );
        return array_values(array_map(function ($r) {
            return $r->shortname;
        }, $roles));
    }

    /**
     * Get active course enrolment count for a user.
     *
     * @param int $userid User ID
     * @return int
     */
    protected function get_user_enrolment_count(int $userid): int {
        if (self::$bulkindexactive) {
            return self::$bulkenrolcounts[$userid] ?? 0;
        }

        global $DB;
        return (int) $DB->count_records_sql(
            "SELECT COUNT(DISTINCT e.courseid)
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE ue.userid = ?
                AND e.status = 0
                AND ue.status = 0",
            [$userid]
        );
    }

    /**
     * Check whether the source user still exists.
     *
     * @param int $itemid User id
     * @return bool
     */
    public function record_exists(int $itemid): bool {
        global $DB;
        return $DB->record_exists('user', ['id' => $itemid, 'deleted' => 0]);
    }
}
