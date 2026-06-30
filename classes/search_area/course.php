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
 * Course search area.
 *
 * @package    local_smartsearch
 * @copyright  2025 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_smartsearch\search_area;


/**
 * Course search area implementation.
 */
class course extends base {
    /**
     * Get the display name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('category_courses', 'local_smartsearch');
    }

    /**
     * Get the recordset of courses to index.
     *
     * @param int $modifiedfrom Timestamp
     * @return \moodle_recordset|null
     */
    public function get_recordset(int $modifiedfrom = 0): ?\moodle_recordset {
        global $DB;

        $params = [];
        $where = 'id > 1'; // Exclude site course.

        if ($modifiedfrom > 0) {
            $where .= ' AND (timecreated > ? OR timemodified > ?)';
            $params[] = $modifiedfrom;
            $params[] = $modifiedfrom;
        }

        return $DB->get_recordset_select(
            'course',
            $where,
            $params,
            'id ASC',
            'id, fullname, shortname, summary, category, visible, idnumber'
        );
    }

    /**
     * Index a course.
     *
     * @param int $itemid Course ID
     * @param \stdClass|null $sourcerecord Optional source row from get_recordset()
     * @return array|null
     */
    public function index_item(int $itemid, ?\stdClass $sourcerecord = null): ?array {
        global $DB;

        $course = $sourcerecord ?? $DB->get_record('course', ['id' => $itemid]);
        if (!$course) {
            return null;
        }

        $keywords = [$course->shortname];
        if (get_config('local_smartsearch', 'search_course_idnumbers') && !empty($course->idnumber)) {
            $keywords[] = $course->idnumber;
        }

        $categorypath = \local_smartsearch\category_path::get_path((int) $course->category);

        $subtitle = '';
        if ($categorypath) {
            $subtitle = $categorypath;
        }

        $courseenrollments = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT ue.userid)
             FROM {user_enrolments} ue
             JOIN {enrol} e ON e.id = ue.enrolid
             WHERE e.courseid = ? AND ue.status = 0",
            [$itemid]
        );
        if ($courseenrollments > 0) {
            if ($subtitle) {
                $subtitle .= ' | ';
            }
            $subtitle .= get_string('enrolledusers', 'enrol') . ': ' . $courseenrollments;
        }

        // Add description if searchable.
        if (get_config('local_smartsearch', 'search_course_descriptions') && !empty($course->summary)) {
            $subtitle .= ($subtitle ? ' | ' : '') . strip_tags($course->summary);
        }

        $courseurl = new \moodle_url('/course/view.php', ['id' => $itemid]);

        return [
            'recordtype' => 'course',
            'recordid' => $itemid,
            'contextpath' => $categorypath,
            'title' => $course->fullname,
            'subtitle' => $subtitle,
            'keywords' => $keywords,
            'url' => $courseurl->out(false),
            'metadata' => [
                'shortname' => $course->shortname,
                'category' => $course->category,
                'visible' => (bool) $course->visible,
                'idnumber' => $course->idnumber,
            ],
        ];
    }

    /**
     * Get course URL.
     *
     * @param int $itemid Course ID
     * @return \moodle_url|null
     */
    public function get_item_url(int $itemid): ?\moodle_url {
        return new \moodle_url('/course/view.php', ['id' => $itemid]);
    }

    /**
     * Get course actions.
     *
     * @param int $itemid Course ID
     * @param int $userid Current user ID
     * @return array
     */
    public function get_item_actions(int $itemid, int $userid): array {
        return \local_smartsearch\actions::get_course_actions($userid, $itemid);
    }

    /**
     * Check if course can be indexed/viewed.
     *
     * @param int $userid Current user ID
     * @param int $itemid Course ID
     * @return bool
     */
    public function can_index(int $userid, int $itemid): bool {
        return \local_smartsearch\permissions::can_view_course($userid, $itemid);
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
        return 'course';
    }

    /**
     * Check whether the source course still exists.
     *
     * @param int $itemid Course id
     * @return bool
     */
    public function record_exists(int $itemid): bool {
        global $DB;
        return $itemid > 1 && $DB->record_exists('course', ['id' => $itemid]);
    }
}
