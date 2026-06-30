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
 * Activity search area.
 *
 * @package    local_smartsearch
 * @copyright  2025 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_smartsearch\search_area;


/**
 * Activity search area implementation.
 */
class activity extends base {
    /** @var array<int, \course_modinfo> */
    private static $modinfocache = [];

    /**
     * Reset per-run modinfo cache (called at the start of bulk indexing).
     */
    public static function reset_bulk_index_cache(): void {
        self::$modinfocache = [];
    }

    /**
     * Get modinfo for a course, cached during bulk indexing.
     *
     * @param int $courseid
     * @return \course_modinfo
     */
    protected function get_modinfo_for_course(int $courseid): \course_modinfo {
        if (!isset(self::$modinfocache[$courseid])) {
            self::$modinfocache[$courseid] = get_fast_modinfo($courseid);
        }
        return self::$modinfocache[$courseid];
    }
    /**
     * Get the display name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('category_activities', 'local_smartsearch');
    }

    /**
     * Get the recordset of activities to index.
     *
     * @param int $modifiedfrom Timestamp
     * @return \moodle_recordset|null
     */
    public function get_recordset(int $modifiedfrom = 0): ?\moodle_recordset {
        global $DB;

        $params = [];
        $where = 'cm.deletioninprogress = 0 AND c.id IS NOT NULL';

        if ($modifiedfrom > 0) {
            // Course_modules has "added" timestamp, not "timemodified".
            $where .= ' AND cm.added > ?';
            $params[] = $modifiedfrom;
        }

        $sql = "SELECT cm.id, cm.course, cm.module, cm.instance, cm.section, cm.visible,
                       m.name as modulename, c.fullname as coursename, s.name as sectionname
                FROM {course_modules} cm
                JOIN {modules} m ON m.id = cm.module AND m.visible = 1
                JOIN {course} c ON c.id = cm.course
                LEFT JOIN {course_sections} s ON s.id = cm.section
                WHERE {$where}
                ORDER BY cm.id ASC";

        return $DB->get_recordset_sql($sql, $params);
    }

    /**
     * Index an activity.
     *
     * @param int $itemid Course module ID
     * @param \stdClass|null $sourcerecord Optional source row from get_recordset()
     * @return array|null
     */
    public function index_item(int $itemid, ?\stdClass $sourcerecord = null): ?array {
        global $DB;

        if ($sourcerecord !== null && isset($sourcerecord->id)) {
            return $this->index_from_record($sourcerecord);
        }

        // Wrap everything in try-catch to handle any unexpected errors gracefully.
        try {
            // Try to get course module, return null if it doesn't exist (don't throw error).
            try {
                $cm = get_coursemodule_from_id('', $itemid, 0, false, IGNORE_MISSING);
                if (!$cm) {
                    // Course module doesn't exist, skip it.
                    return null;
                }
            } catch (\Exception $e) {
                // Course module doesn't exist or is invalid, skip it.
                return null;
            }

            // Check if course exists.
            $course = $DB->get_record('course', ['id' => $cm->course]);
            if (!$course) {
                // Course doesn't exist, skip this activity.
                return null;
            }

            // Get modinfo, return null if it fails.
            try {
                $modinfo = $this->get_modinfo_for_course((int) $cm->course);
                if (!$modinfo->cms || !isset($modinfo->cms[$itemid])) {
                    return null;
                }
                $cminfo = $modinfo->cms[$itemid];
            } catch (\Exception $e) {
                // Can't get modinfo or course module info, skip it.
                return null;
            }

            // Get section, but don't fail if it doesn't exist.
            $section = $DB->get_record('course_sections', ['id' => $cm->section]);

            $contextpath = $course->fullname;
            if ($section) {
                if (!empty($section->name)) {
                    $sectionname = $section->name;
                } else {
                    // Use our string for unnamed sections.
                    $sectionname = get_string('section', 'local_smartsearch') . ' ' . $section->section;
                }
                $contextpath .= ' > ' . $sectionname;
            }

            $keywords = [$cminfo->modname, $cminfo->name];

            // Get URL, fallback to course view if activity URL is not available.
            $url = '/course/view.php';
            if (isset($cminfo->url) && $cminfo->url !== null) {
                $url = $cminfo->url->out(false);
            } else {
                // Fallback: create URL to course.
                $courseurl = new \moodle_url('/course/view.php', ['id' => $cm->course]);
                $url = $courseurl->out(false);
            }

            return [
                'recordtype' => 'activity',
                'recordid' => $itemid,
                'contextpath' => $contextpath,
                'title' => $cminfo->name,
                'subtitle' => get_string('modulename', $cminfo->modname) . ' | '
                    . get_string('in', 'local_smartsearch') . ': ' . $contextpath,
                'keywords' => $keywords,
                'url' => $url,
                'metadata' => [
                    'course' => $cm->course,
                    'modulename' => $cminfo->modname,
                    'instance' => $cm->instance,
                    'visible' => (bool) $cm->visible,
                ],
            ];
        } catch (\Exception $e) {
            // Catch any unexpected errors and return null (skip this item).
            // This prevents indexing from failing due to orphaned or invalid course modules.
            return null;
        }
    }

    /**
     * Index an activity from a recordset row (avoids duplicate DB/modinfo lookups).
     *
     * @param \stdClass $record Recordset row from get_recordset()
     * @return array|null
     */
    protected function index_from_record(\stdClass $record): ?array {
        try {
            $itemid = (int) $record->id;
            $courseid = (int) ($record->course ?? 0);
            if ($courseid <= 0) {
                return null;
            }

            // Index all course modules; per-user visibility is enforced in can_index() at search time.
            $modinfo = $this->get_modinfo_for_course($courseid);
            if (!$modinfo->cms || !isset($modinfo->cms[$itemid])) {
                return null;
            }
            $cminfo = $modinfo->cms[$itemid];

            $contextpath = $record->coursename ?? '';
            if (!empty($record->sectionname)) {
                $contextpath .= ' > ' . $record->sectionname;
            } else if (!empty($record->section)) {
                $contextpath .= ' > ' . get_string('section', 'local_smartsearch') . ' ' . $record->section;
            }

            $keywords = [$cminfo->modname, $cminfo->name];
            $url = '/course/view.php';
            if (isset($cminfo->url) && $cminfo->url !== null) {
                $url = $cminfo->url->out(false);
            } else {
                $url = (new \moodle_url('/course/view.php', ['id' => $courseid]))->out(false);
            }

            return [
                'recordtype' => 'activity',
                'recordid' => $itemid,
                'contextpath' => $contextpath,
                'title' => $cminfo->name,
                'subtitle' => get_string('modulename', $cminfo->modname) . ' | '
                    . get_string('in', 'local_smartsearch') . ': ' . $contextpath,
                'keywords' => $keywords,
                'url' => $url,
                'metadata' => [
                    'course' => $courseid,
                    'modulename' => $cminfo->modname,
                    'instance' => (int) ($record->instance ?? 0),
                    'visible' => (bool) ($record->visible ?? 0),
                ],
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get activity URL.
     *
     * @param int $itemid Course module ID
     * @return \moodle_url|null
     */
    public function get_item_url(int $itemid): ?\moodle_url {
        try {
            $cm = get_coursemodule_from_id('', $itemid, 0, false, IGNORE_MISSING);
            if (!$cm) {
                return null;
            }

            $modinfo = get_fast_modinfo($cm->course);
            $cminfo = $modinfo->get_cm($itemid);

            if (!$cminfo || !isset($cminfo->url) || $cminfo->url === null) {
                // Fallback to course view.
                return new \moodle_url('/course/view.php', ['id' => $cm->course]);
            }

            return $cminfo->url;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get activity actions.
     *
     * @param int $itemid Course module ID
     * @param int $userid Current user ID
     * @return array
     */
    public function get_item_actions(int $itemid, int $userid): array {
        return \local_smartsearch\actions::get_activity_actions($userid, $itemid);
    }

    /**
     * Check if activity can be indexed/viewed.
     *
     * @param int $userid Current user ID
     * @param int $itemid Course module ID
     * @return bool
     */
    public function can_index(int $userid, int $itemid): bool {
        return \local_smartsearch\permissions::can_view_activity($userid, $itemid);
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
        return 'activity';
    }

    /**
     * Check whether the source activity still exists.
     *
     * @param int $itemid Course-module id
     * @return bool
     */
    public function record_exists(int $itemid): bool {
        global $DB;
        return $DB->record_exists('course_modules', ['id' => $itemid, 'deletioninprogress' => 0]);
    }
}
