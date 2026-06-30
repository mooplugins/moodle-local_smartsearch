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
 * Cached course category path builder.
 *
 * @package    local_smartsearch
 * @copyright  2025 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_smartsearch;


/**
 * Builds category breadcrumb paths without per-level DB queries.
 */
class category_path {
    /** @var array<int, \stdClass>|null */
    private static $categories = null;

    /**
     * Reset the in-memory category cache (e.g. during bulk indexing).
     */
    public static function reset_cache(): void {
        self::$categories = null;
    }

    /**
     * Build a human-readable path for a category id.
     *
     * @param int $categoryid Category id
     * @return string
     */
    public static function get_path(int $categoryid): string {
        if ($categoryid <= 0) {
            return '';
        }

        self::load_categories();

        $path = [];
        $currentid = $categoryid;
        $guard = 0;

        while ($currentid > 0 && isset(self::$categories[$currentid]) && $guard < 50) {
            $guard++;
            $category = self::$categories[$currentid];
            array_unshift($path, $category->name);
            $currentid = (int) $category->parent;
        }

        return implode(' > ', $path);
    }

    /**
     * Load all categories once per request/index run.
     */
    private static function load_categories(): void {
        if (self::$categories !== null) {
            return;
        }

        global $DB;
        self::$categories = $DB->get_records('course_categories', null, '', 'id, name, parent');
    }
}
