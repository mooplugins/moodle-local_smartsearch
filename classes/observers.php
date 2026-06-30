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
 * Event observers for Smart Search.
 *
 * @package    local_smartsearch
 * @copyright  2025 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_smartsearch;


/**
 * Event observers.
 */
class observers {
    /**
     * Handle user created event.
     *
     * @param \core\event\user_created $event
     */
    public static function user_created(\core\event\user_created $event) {
        if (!get_config('local_smartsearch', 'enable')) {
            return;
        }
        indexer::index_item('user', $event->objectid);
    }

    /**
     * Handle user updated event.
     *
     * @param \core\event\user_updated $event
     */
    public static function user_updated(\core\event\user_updated $event) {
        if (!get_config('local_smartsearch', 'enable')) {
            return;
        }
        indexer::index_item('user', $event->objectid);
    }

    /**
     * Handle user deleted event.
     *
     * @param \core\event\user_deleted $event
     */
    public static function user_deleted(\core\event\user_deleted $event) {
        if (!get_config('local_smartsearch', 'enable')) {
            return;
        }
        indexer::remove_from_index('user', $event->objectid);
    }

    /**
     * Handle course created event.
     *
     * @param \core\event\course_created $event
     */
    public static function course_created(\core\event\course_created $event) {
        if (!get_config('local_smartsearch', 'enable')) {
            return;
        }
        indexer::index_item('course', $event->objectid);
    }

    /**
     * Handle course updated event.
     *
     * @param \core\event\course_updated $event
     */
    public static function course_updated(\core\event\course_updated $event) {
        if (!get_config('local_smartsearch', 'enable')) {
            return;
        }
        indexer::index_item('course', $event->objectid);
    }

    /**
     * Handle course deleted event.
     *
     * @param \core\event\course_deleted $event
     */
    public static function course_deleted(\core\event\course_deleted $event) {
        if (!get_config('local_smartsearch', 'enable')) {
            return;
        }
        indexer::remove_from_index('course', $event->objectid);
    }

    /**
     * Handle course module created event.
     *
     * @param \core\event\course_module_created $event
     */
    public static function course_module_created(\core\event\course_module_created $event) {
        if (!get_config('local_smartsearch', 'enable')) {
            return;
        }
        indexer::index_item('activity', $event->contextinstanceid);
    }

    /**
     * Handle course module updated event.
     *
     * @param \core\event\course_module_updated $event
     */
    public static function course_module_updated(\core\event\course_module_updated $event) {
        if (!get_config('local_smartsearch', 'enable')) {
            return;
        }
        indexer::index_item('activity', $event->contextinstanceid);
    }

    /**
     * Handle course module deleted event.
     *
     * @param \core\event\course_module_deleted $event
     */
    public static function course_module_deleted(\core\event\course_module_deleted $event) {
        if (!get_config('local_smartsearch', 'enable')) {
            return;
        }
        indexer::remove_from_index('activity', $event->contextinstanceid);
    }

    /**
     * Handle course category created event.
     *
     * @param \core\event\course_category_created $event
     */
    public static function course_category_created(\core\event\course_category_created $event) {
        if (!get_config('local_smartsearch', 'enable')) {
            return;
        }
        indexer::index_item('category', $event->objectid);
    }

    /**
     * Handle course category updated event.
     *
     * @param \core\event\course_category_updated $event
     */
    public static function course_category_updated(\core\event\course_category_updated $event) {
        if (!get_config('local_smartsearch', 'enable')) {
            return;
        }
        indexer::index_item('category', $event->objectid);
    }

    /**
     * Handle course category deleted event.
     *
     * @param \core\event\course_category_deleted $event
     */
    public static function course_category_deleted(\core\event\course_category_deleted $event) {
        if (!get_config('local_smartsearch', 'enable')) {
            return;
        }
        indexer::remove_from_index('category', $event->objectid);
    }
}
