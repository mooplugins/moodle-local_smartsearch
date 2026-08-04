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
 * External API for Smart Search indexing controls.
 *
 * @package    local_smartsearch
 * @copyright  2026 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_smartsearch\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * External indexing API.
 */
class index_external extends \external_api {
    /**
     * Parameters for start_indexing.
     *
     * @return \external_function_parameters
     */
    public static function start_indexing_parameters() {
        return new \external_function_parameters([]);
    }

    /**
     * Return structure for start_indexing.
     *
     * @return \external_single_structure
     */
    public static function start_indexing_returns() {
        return new \external_single_structure([
            'status' => new \external_value(PARAM_ALPHAEXT, 'started or already_running'),
        ]);
    }

    /**
     * Queue a full reindex adhoc task.
     *
     * @return array
     */
    public static function start_indexing() {
        self::validate_parameters(self::start_indexing_parameters(), []);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/smartsearch:manage', $context);

        $inprogress = get_config('local_smartsearch', 'indexing_in_progress');
        if (!empty($inprogress)) {
            return ['status' => 'already_running'];
        }

        set_config('indexing_in_progress', time(), 'local_smartsearch');
        set_config('indexing_stats', json_encode([
            'total' => 0,
            'indexed' => 0,
            'skipped' => 0,
            'errors' => 0,
        ]), 'local_smartsearch');

        $task = new \local_smartsearch\task\index_adhoc_task();
        \core\task\manager::queue_adhoc_task($task, true);

        return ['status' => 'started'];
    }

    /**
     * Parameters for get_indexing_progress.
     *
     * @return \external_function_parameters
     */
    public static function get_indexing_progress_parameters() {
        return new \external_function_parameters([]);
    }

    /**
     * Return structure for get_indexing_progress.
     *
     * @return \external_single_structure
     */
    public static function get_indexing_progress_returns() {
        return new \external_single_structure([
            'in_progress' => new \external_value(PARAM_BOOL, 'Whether indexing is running'),
            'stats' => new \external_value(PARAM_RAW, 'JSON encoded indexing stats', VALUE_DEFAULT, ''),
            'timestamp' => new \external_value(PARAM_INT, 'Server time when progress was read'),
        ]);
    }

    /**
     * Return current indexing progress.
     *
     * @return array
     */
    public static function get_indexing_progress() {
        self::validate_parameters(self::get_indexing_progress_parameters(), []);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/smartsearch:manage', $context);

        $statsjson = get_config('local_smartsearch', 'indexing_stats');
        $inprogress = get_config('local_smartsearch', 'indexing_in_progress');

        $stats = null;
        if (!empty($statsjson)) {
            $decoded = @json_decode($statsjson, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $stats = $decoded;
            }
        }

        if (!empty($inprogress) && $stats === null) {
            $stats = [
                'total' => 0,
                'indexed' => 0,
                'skipped' => 0,
                'errors' => 0,
            ];
        }

        return [
            'in_progress' => !empty($inprogress),
            'stats' => $stats === null ? '' : json_encode($stats),
            'timestamp' => time(),
        ];
    }

    /**
     * Parameters for stop_indexing.
     *
     * @return \external_function_parameters
     */
    public static function stop_indexing_parameters() {
        return new \external_function_parameters([]);
    }

    /**
     * Return structure for stop_indexing.
     *
     * @return \external_single_structure
     */
    public static function stop_indexing_returns() {
        return new \external_single_structure([
            'status' => new \external_value(PARAM_ALPHA, 'stopped'),
        ]);
    }

    /**
     * Request indexing stop (adhoc task exits on next progress check).
     *
     * @return array
     */
    public static function stop_indexing() {
        self::validate_parameters(self::stop_indexing_parameters(), []);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/smartsearch:manage', $context);

        unset_config('indexing_in_progress', 'local_smartsearch');

        return ['status' => 'stopped'];
    }
}
