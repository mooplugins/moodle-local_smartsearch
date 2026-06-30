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
 * Cleanup task for Smart Search.
 *
 * @package    local_smartsearch
 * @copyright  2025 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_smartsearch\task;


/**
 * Scheduled task for cleanup operations.
 */
class cleanup_task extends \core\task\scheduled_task {
    /**
     * Get task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_cleanup', 'local_smartsearch');
    }

    /**
     * Execute task.
     */
    public function execute() {
        if (!get_config('local_smartsearch', 'enable')) {
            return;
        }

        // Clean up old analytics data.
        \local_smartsearch\analytics::cleanup_old_data();

        // Clean up orphaned index entries (handled in indexer).
        // This is done during indexing, but we can also do it here periodically.
    }
}
