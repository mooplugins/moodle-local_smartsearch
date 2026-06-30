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
 * Permissions class for Smart Search.
 *
 * @package    local_smartsearch
 * @copyright  2025 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_smartsearch;


/**
 * Handles permission checks using Moodle's capability system.
 */
class permissions {
    /** @var array<string, int> */
    private static $sharedcoursecache = [];

    /** @var array<string, bool> */
    private static $membershipcache = [];

    /** @var array<string, bool> */
    private static $categoryaccesscache = [];

    /** @var array<string, ?\cm_info> */
    private static $cminfocache = [];

    /** @var array<string, bool> */
    private static $courseviewcache = [];

    /** @var array<string, bool> */
    private static $canviewusercache = [];

    /** @var array<int, \stdClass|null> */
    private static $courserecordcache = [];

    /**
     * Reset per-request caches used during search result filtering.
     */
    public static function begin_search_context(): void {
        self::$sharedcoursecache = [];
        self::$membershipcache = [];
        self::$categoryaccesscache = [];
        self::$cminfocache = [];
        self::$courseviewcache = [];
        self::$canviewusercache = [];
        self::$courserecordcache = [];
    }

    /**
     * Whether user is a site admin.
     *
     * @param int $userid
     * @return bool
     */
    protected static function is_site_admin(int $userid): bool {
        return is_siteadmin($userid);
    }

    /**
     * Check if a user can search.
     *
     * @param int $userid The user ID
     * @return bool
     */
    public static function can_search(int $userid): bool {
        if (self::is_site_admin($userid)) {
            return true;
        }
        return has_capability('local/smartsearch:search', \context_system::instance(), $userid);
    }

    /**
     * Check if a user can manage Smart Search.
     *
     * @param int $userid The user ID
     * @return bool
     */
    public static function can_manage(int $userid): bool {
        if (self::is_site_admin($userid)) {
            return true;
        }
        return has_capability('local/smartsearch:manage', \context_system::instance(), $userid);
    }

    /**
     * Check if a user can view email addresses.
     *
     * @param int $userid The user ID
     * @return bool
     */
    public static function can_view_emails(int $userid): bool {
        if (self::is_site_admin($userid)) {
            return true;
        }
        return has_capability('moodle/user:viewalldetails', \context_system::instance(), $userid);
    }

    /**
     * Check if a user can view a specific user.
     *
     * @param int $userid The user ID
     * @param int $targetuserid The target user ID
     * @return bool
     */
    public static function can_view_user(int $userid, int $targetuserid): bool {
        if (self::is_site_admin($userid)) {
            return true;
        }

        $cachekey = $userid . ':' . $targetuserid;
        if (array_key_exists($cachekey, self::$canviewusercache)) {
            return self::$canviewusercache[$cachekey];
        }

        $allowed = false;
        try {
            if ($userid == $targetuserid) {
                $allowed = true;
            } else {
                $context = \context_user::instance($targetuserid);
                if (has_capability('moodle/user:viewdetails', $context, $userid)) {
                    $allowed = true;
                } else {
                    $allowed = self::get_shared_visible_course_for_users($userid, $targetuserid) > 0;
                }
            }
        } catch (\Exception $e) {
            $allowed = false;
        }

        self::$canviewusercache[$cachekey] = $allowed;
        return $allowed;
    }

    /**
     * Build the best available profile URL for a user result.
     * Uses course-context profile when system profile is not accessible.
     *
     * @param int $viewerid Current user ID
     * @param int $targetuserid Target user ID
     * @return \moodle_url
     */
    public static function get_user_result_url(int $viewerid, int $targetuserid): \moodle_url {
        // Own profile can always use the standard profile page.
        if ($viewerid === $targetuserid) {
            return new \moodle_url('/user/profile.php', ['id' => $targetuserid]);
        }

        // If user has direct user-details access, use normal profile.
        try {
            $usercontext = \context_user::instance($targetuserid);
            if (has_capability('moodle/user:viewdetails', $usercontext, $viewerid)) {
                return new \moodle_url('/user/profile.php', ['id' => $targetuserid]);
            }
        } catch (\Exception $e) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
            // Ignore and try course-context fallback.
        }

        // Fallback to any shared active course where viewer can see participant details.
        $sharedcourseid = self::get_shared_visible_course_for_users($viewerid, $targetuserid);
        if ($sharedcourseid) {
            return new \moodle_url('/user/view.php', ['id' => $targetuserid, 'course' => $sharedcourseid]);
        }

        // Final fallback.
        return new \moodle_url('/user/profile.php', ['id' => $targetuserid]);
    }

    /**
     * Check if a user can view a course.
     *
     * @param int $userid The user ID
     * @param int $courseid The course ID
     * @return bool
     */
    public static function can_view_course(int $userid, int $courseid): bool {
        if (self::is_site_admin($userid)) {
            return true;
        }

        $cachekey = $userid . ':' . $courseid;
        if (array_key_exists($cachekey, self::$courseviewcache)) {
            return self::$courseviewcache[$cachekey];
        }

        $allowed = false;
        try {
            $course = self::get_course_for_access_check($courseid);
            if (!$course) {
                self::$courseviewcache[$cachekey] = false;
                return false;
            }

            $context = \context_course::instance($courseid);
            if (!$course->visible && !has_capability('moodle/course:viewhiddencourses', $context, $userid)) {
                self::$courseviewcache[$cachekey] = false;
                return false;
            }

            if (self::has_course_membership($userid, $courseid)) {
                $allowed = true;
            } else {
                $allowed = has_capability('moodle/course:view', $context, $userid);
            }
        } catch (\Exception $e) {
            $allowed = false;
        }

        self::$courseviewcache[$cachekey] = $allowed;
        return $allowed;
    }

    /**
     * Load minimal course row for access checks (cached per request).
     *
     * @param int $courseid
     * @return \stdClass|null
     */
    protected static function get_course_for_access_check(int $courseid): ?\stdClass {
        if (!array_key_exists($courseid, self::$courserecordcache)) {
            global $DB;
            self::$courserecordcache[$courseid] = $DB->get_record('course', ['id' => $courseid], 'id, visible') ?: null;
        }
        return self::$courserecordcache[$courseid];
    }

    /**
     * Check if user is associated with a course via active enrolment
     * or direct role assignment in course context.
     *
     * @param int $userid
     * @param int $courseid
     * @return bool
     */
    public static function has_course_membership(int $userid, int $courseid): bool {
        if (self::is_site_admin($userid)) {
            return true;
        }

        $cachekey = $userid . ':' . $courseid;
        if (array_key_exists($cachekey, self::$membershipcache)) {
            return self::$membershipcache[$cachekey];
        }

        global $DB;

        $isenrolled = $DB->record_exists_sql(
            "SELECT 1
               FROM {enrol} e
               JOIN {user_enrolments} ue ON ue.enrolid = e.id
              WHERE e.courseid = ?
                AND ue.userid = ?
                AND e.status = 0
                AND ue.status = 0",
            [$courseid, $userid]
        );
        if ($isenrolled) {
            self::$membershipcache[$cachekey] = true;
            return true;
        }

        $hasrole = $DB->record_exists_sql(
            "SELECT 1
               FROM {role_assignments} ra
               JOIN {context} ctx ON ctx.id = ra.contextid
              WHERE ra.userid = ?
                AND ctx.contextlevel = ?
                AND ctx.instanceid = ?",
            [$userid, CONTEXT_COURSE, $courseid]
        );
        self::$membershipcache[$cachekey] = $hasrole;
        return $hasrole;
    }

    /**
     * Get course-module info for a user if they can view it (cached per request).
     *
     * @param int $userid User id
     * @param int $cmid Course-module id
     * @return \cm_info|null
     */
    public static function get_cm_info_for_user(int $userid, int $cmid): ?\cm_info {
        if (self::is_site_admin($userid)) {
            try {
                $cm = get_coursemodule_from_id('', $cmid, 0, false, IGNORE_MISSING);
                if (!$cm) {
                    return null;
                }
                $modinfo = get_fast_modinfo((int) $cm->course, $userid);
                return $modinfo->get_cm($cmid);
            } catch (\Exception $e) {
                return null;
            }
        }

        $cachekey = $userid . ':' . $cmid;
        if (array_key_exists($cachekey, self::$cminfocache)) {
            return self::$cminfocache[$cachekey];
        }

        $result = null;
        try {
            $cm = get_coursemodule_from_id('', $cmid, 0, false, IGNORE_MISSING);
            if (!$cm) {
                self::$cminfocache[$cachekey] = null;
                return null;
            }

            if (!self::can_view_course($userid, (int) $cm->course)) {
                self::$cminfocache[$cachekey] = null;
                return null;
            }

            $modinfo = get_fast_modinfo((int) $cm->course, $userid);
            $cminfo = $modinfo->get_cm($cmid);
            if ($cminfo && $cminfo->uservisible) {
                $result = $cminfo;
            }
        } catch (\Exception $e) {
            $result = null;
        }

        self::$cminfocache[$cachekey] = $result;
        return $result;
    }

    /**
     * Check if a user can view an activity.
     *
     * @param int $userid The user ID
     * @param int $cmid The course module ID
     * @return bool True if user can view the activity, false otherwise
     */
    public static function can_view_activity(int $userid, int $cmid): bool {
        if (self::is_site_admin($userid)) {
            return true;
        }
        return self::get_cm_info_for_user($userid, $cmid) !== null;
    }

    /**
     * Check if a user can access site settings.
     *
     * @param int $userid The user ID
     * @return bool
     */
    public static function can_access_settings(int $userid): bool {
        if (self::is_site_admin($userid)) {
            return true;
        }
        return has_capability('moodle/site:config', \context_system::instance(), $userid);
    }

    /**
     * Check if a user can access custom courses management page.
     *
     * @param int $userid
     * @return bool
     */
    public static function can_access_courses_management_page(int $userid): bool {
        if (self::is_site_admin($userid)) {
            return true;
        }
        $context = \context_system::instance();
        return has_any_capability(['moodle/category:manage', 'moodle/course:create'], $context, $userid);
    }

    /**
     * Find a shared active course where viewer can see target user's details.
     *
     * @param int $viewerid
     * @param int $targetuserid
     * @return int Course ID or 0 when none found
     */
    protected static function get_shared_visible_course_for_users(int $viewerid, int $targetuserid): int {
        global $DB;

        $cachekey = $viewerid . ':' . $targetuserid;
        if (array_key_exists($cachekey, self::$sharedcoursecache)) {
            return self::$sharedcoursecache[$cachekey];
        }

        $coursesql = "SELECT c.id AS courseid
               FROM {course} c
              WHERE c.id <> 1
                AND (
                    EXISTS (
                        SELECT 1
                          FROM {user_enrolments} ue
                          JOIN {enrol} e ON e.id = ue.enrolid
                         WHERE ue.userid = ?
                           AND e.courseid = c.id
                           AND e.status = 0
                           AND ue.status = 0
                    )
                    OR EXISTS (
                        SELECT 1
                          FROM {role_assignments} ra
                          JOIN {context} ctx ON ctx.id = ra.contextid
                         WHERE ra.userid = ?
                           AND ctx.contextlevel = ?
                           AND ctx.instanceid = c.id
                    )
                )
                AND (
                    EXISTS (
                        SELECT 1
                          FROM {user_enrolments} ue
                          JOIN {enrol} e ON e.id = ue.enrolid
                         WHERE ue.userid = ?
                           AND e.courseid = c.id
                           AND e.status = 0
                           AND ue.status = 0
                    )
                    OR EXISTS (
                        SELECT 1
                          FROM {role_assignments} ra
                          JOIN {context} ctx ON ctx.id = ra.contextid
                         WHERE ra.userid = ?
                           AND ctx.contextlevel = ?
                           AND ctx.instanceid = c.id
                    )
                )
           ORDER BY c.sortorder ASC, c.id ASC";

        $params = [$viewerid, $viewerid, CONTEXT_COURSE, $targetuserid, $targetuserid, CONTEXT_COURSE];
        $recordset = $DB->get_recordset_sql($coursesql, $params);
        foreach ($recordset as $course) {
            $courseid = (int) $course->courseid;
            if ($courseid <= 0) {
                continue;
            }
            try {
                $coursecontext = \context_course::instance($courseid);
                if (
                    has_capability('moodle/user:viewdetails', $coursecontext, $viewerid)
                    || has_capability('moodle/course:viewparticipants', $coursecontext, $viewerid)
                ) {
                    $recordset->close();
                    self::$sharedcoursecache[$cachekey] = $courseid;
                    return $courseid;
                }
            } catch (\Exception $e) {
                continue;
            }
        }
        $recordset->close();
        self::$sharedcoursecache[$cachekey] = 0;
        return 0;
    }

    /**
     * Whether a search/action URL is complete enough to link to.
     *
     * @param string|\moodle_url|null $url
     * @return bool
     */
    public static function is_usable_result_url($url): bool {
        if ($url === null) {
            return false;
        }

        if ($url instanceof \moodle_url) {
            [$path, $params] = self::normalize_url_parts($url);
        } else if (is_string($url)) {
            $trimmed = trim($url);
            if ($trimmed === '' || $trimmed === '#' || $trimmed === '/') {
                return false;
            }
            $parsed = \local_smartsearch\query::parse_stored_url($trimmed);
            if ($parsed === null) {
                return false;
            }
            return self::is_usable_result_url($parsed);
        } else {
            return false;
        }

        if ($path === '/admin/category.php' && empty($params['category'])) {
            return false;
        }

        if ($path === '/admin/settings.php' && empty($params['section'])) {
            return false;
        }

        return true;
    }

    /**
     * Normalize a moodle_url into site-relative path and query params.
     *
     * @param \moodle_url $url
     * @return array{0: string, 1: array}
     */
    protected static function normalize_url_parts(\moodle_url $url): array {
        global $CFG;

        $path = $url->get_path(false);
        $params = $url->params();

        if (!empty($CFG->wwwroot)) {
            $wwwpath = (string) (parse_url($CFG->wwwroot, PHP_URL_PATH) ?: '');
            if ($wwwpath !== '' && strpos($path, $wwwpath) === 0) {
                $path = substr($path, strlen($wwwpath)) ?: '/';
            }
        }

        $path = '/' . ltrim((string) $path, '/');

        return [$path, $params];
    }

    /**
     * Whether the user may receive a search hit that links to this URL.
     * Filters admin-only destinations (e.g. Smart Search settings) that were indexed for everyone.
     *
     * @param int $userid
     * @param string|\moodle_url|null $url Stored/final URL for the result
     * @return bool
     */
    public static function can_access_search_result_url(int $userid, $url): bool {
        if (self::is_site_admin($userid)) {
            return true;
        }
        if ($url instanceof \moodle_url) {
            $url = $url->out(false);
        }
        if (!is_string($url) || $url === '' || $url === '#' || $url === '/') {
            return true;
        }

        if (!self::is_usable_result_url($url)) {
            return false;
        }

        $path = $url;
        if (preg_match('#^https?://[^/]+(/.*)$#', $path, $m)) {
            $path = $m[1];
        }
        global $CFG;
        if (!empty($CFG->wwwroot)) {
            $wwwpath = (string) (parse_url($CFG->wwwroot, PHP_URL_PATH) ?: '');
            if ($wwwpath !== '' && strpos($path, $wwwpath) === 0) {
                $path = substr($path, strlen($wwwpath)) ?: '/';
            }
        }
        $qpos = strpos($path, '?');
        if ($qpos !== false) {
            $path = substr($path, 0, $qpos);
        }
        $path = '/' . ltrim($path, '/');

        if (
            strpos($path, '/local/smartsearch/settings') === 0
                || strpos($path, '/local/smartsearch/report') === 0
        ) {
            return self::can_manage($userid);
        }
        if (
            strpos($path, '/admin/settings.php') === 0
                || strpos($path, '/admin/category.php') === 0
        ) {
            return self::can_access_settings($userid);
        }
        if (strpos($path, '/catalog/index.php') !== false) {
            $query = parse_url((string)$url, PHP_URL_QUERY);
            if (is_string($query) && $query !== '') {
                $params = [];
                parse_str($query, $params);
                $rawcategory = $params['category'] ?? ($params['categoryid'] ?? null);
                if (!empty($rawcategory)) {
                    $categoryid = (int)$rawcategory;
                    if ($categoryid > 0) {
                        return self::has_accessible_course_in_category($userid, $categoryid);
                    }
                }
            }
        }

        return true;
    }

    /**
     * Check whether user can access at least one course in category tree.
     *
     * @param int $userid
     * @param int $categoryid
     * @return bool
     */
    protected static function has_accessible_course_in_category(int $userid, int $categoryid): bool {
        if (self::is_site_admin($userid)) {
            return true;
        }

        $cachekey = $userid . ':' . $categoryid;
        if (array_key_exists($cachekey, self::$categoryaccesscache)) {
            return self::$categoryaccesscache[$cachekey];
        }

        global $DB;

        $category = $DB->get_record('course_categories', ['id' => $categoryid], 'id');
        if (!$category) {
            self::$categoryaccesscache[$cachekey] = false;
            return false;
        }

        $categorycontext = \context_coursecat::instance($categoryid);
        $isteacherstyle = has_capability('moodle/course:update', $categorycontext, $userid);

        $sql = "SELECT 1
                  FROM {course} c
                 WHERE c.category = ?
                   AND c.id > 1
                   AND (
                       ? = 1
                       OR c.visible = 1
                   )
                   AND (
                       EXISTS (
                           SELECT 1
                             FROM {enrol} e
                             JOIN {user_enrolments} ue ON ue.enrolid = e.id
                            WHERE e.courseid = c.id
                              AND ue.userid = ?
                              AND e.status = 0
                              AND ue.status = 0
                       )
                       OR EXISTS (
                           SELECT 1
                             FROM {role_assignments} ra
                             JOIN {context} ctx ON ctx.id = ra.contextid
                            WHERE ra.userid = ?
                              AND ctx.contextlevel = ?
                              AND ctx.instanceid = c.id
                       )
                   )";

        $accessible = $DB->record_exists_sql(
            $sql,
            [$categoryid, $isteacherstyle ? 1 : 0, $userid, $userid, CONTEXT_COURSE]
        );

        self::$categoryaccesscache[$cachekey] = $accessible;
        return $accessible;
    }
}
