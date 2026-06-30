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
 * Analytics class for Smart Search.
 *
 * @package    local_smartsearch
 * @copyright  2025 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_smartsearch;


/**
 * Handles anonymous search analytics.
 */
class analytics {
    /**
     * Log a search query (anonymous).
     *
     * @param string $query The search query
     * @param int $resultcount The number of results
     * @return bool Success
     */
    public static function log_search(string $query, int $resultcount): bool {
        global $DB;

        if (!get_config('local_smartsearch', 'enable_analytics')) {
            return false;
        }

        $record = (object) [
            'query' => $query,
            'result_count' => $resultcount,
            'clicked_result_id' => null,
            'timestamp' => time(),
        ];

        return $DB->insert_record('local_smartsearch_log', $record) !== false;
    }

    /**
     * Clean up old analytics data.
     *
     * @return int Number of records deleted
     */
    public static function cleanup_old_data(): int {
        global $DB;

        if (!get_config('local_smartsearch', 'enable_analytics')) {
            return 0;
        }

        $retentiondays = get_config('local_smartsearch', 'analytics_retention');
        if ($retentiondays === false || $retentiondays === null || $retentiondays === '') {
            $retentiondays = 365; // Default 1 year for unset config.
        }

        $retentiondays = (int) $retentiondays;
        if ($retentiondays === 0) {
            return 0; // Indefinite retention.
        }
        if ($retentiondays < 0) {
            $retentiondays = 365; // Sanity fallback.
        }

        $cutoff = time() - ($retentiondays * 86400);

        return $DB->delete_records_select('local_smartsearch_log', 'timestamp < ?', [$cutoff]);
    }
}
