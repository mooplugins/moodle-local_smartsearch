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
 * @author     BitKea Technologies LLP
 * @copyright  2026 BitKea Technologies LLP
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_smartsearch;


/**
 * Handles search analytics.
 */
class analytics {
    /** Default number of days to keep a search query. */
    public const DEFAULT_RETENTION_DAYS = 365;

    /** Longest retention a site can store. */
    public const MAX_RETENTION_DAYS = 3650;

    /**
     * Log a search query for the current user.
     *
     * Guest and not-logged-in searches are stored without a user id and are removed by retention only.
     *
     * @param string $query The search query
     * @param int $resultcount The number of results
     * @return bool Success
     */
    public static function log_search(string $query, int $resultcount): bool {
        global $DB, $USER;

        if (!get_config('local_smartsearch', 'enable_analytics')) {
            return false;
        }

        $userid = null;
        if (!empty($USER->id) && !isguestuser($USER)) {
            $userid = (int) $USER->id;
        }

        $record = (object) [
            'userid' => $userid,
            'query' => $query,
            'result_count' => $resultcount,
            'clicked_result_id' => null,
            'timestamp' => time(),
        ];

        return $DB->insert_record('local_smartsearch_log', $record) !== false;
    }

    /**
     * Convert a configured retention value into a number of days that will actually expire.
     *
     * Values below 1, including the old "0 means forever" setting, become the default.
     * Values above the maximum are capped.
     *
     * @param mixed $retentiondays Configured retention.
     * @return int Days to keep search queries.
     */
    public static function normalise_retention($retentiondays): int {
        if ($retentiondays === false || $retentiondays === null || $retentiondays === '') {
            return self::DEFAULT_RETENTION_DAYS;
        }

        $retentiondays = (int) $retentiondays;
        if ($retentiondays < 1) {
            return self::DEFAULT_RETENTION_DAYS;
        }
        if ($retentiondays > self::MAX_RETENTION_DAYS) {
            return self::MAX_RETENTION_DAYS;
        }

        return $retentiondays;
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

        $retentiondays = self::normalise_retention(get_config('local_smartsearch', 'analytics_retention'));
        $cutoff = time() - ($retentiondays * DAYSECS);

        return $DB->delete_records_select('local_smartsearch_log', 'timestamp < ?', [$cutoff]);
    }
}
