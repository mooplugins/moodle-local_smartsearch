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
 * @author     BitKea Technologies LLP
 * @copyright  2026 BitKea Technologies LLP
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
     * Begin a resumable chunked full reindex.
     *
     * @return array
     */
    public static function start_indexing() {
        self::validate_parameters(self::start_indexing_parameters(), []);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/smartsearch:manage', $context);

        return \local_smartsearch\indexer::begin_chunked_index();
    }

    /**
     * Parameters for process_indexing_chunk.
     *
     * @return \external_function_parameters
     */
    public static function process_indexing_chunk_parameters() {
        return new \external_function_parameters([
            'chunksize' => new \external_value(PARAM_INT, 'Items per chunk', VALUE_DEFAULT, 100),
        ]);
    }

    /**
     * Return structure for process_indexing_chunk.
     *
     * @return \external_single_structure
     */
    public static function process_indexing_chunk_returns() {
        return new \external_single_structure([
            'done' => new \external_value(PARAM_BOOL, 'Whether indexing finished'),
            'in_progress' => new \external_value(PARAM_BOOL, 'Whether indexing is still running'),
            'stats' => new \external_value(PARAM_RAW, 'JSON encoded indexing stats'),
            'timestamp' => new \external_value(PARAM_INT, 'Server time when chunk finished'),
        ]);
    }

    /**
     * Process the next indexing chunk.
     *
     * @param int $chunksize Items to process
     * @return array
     */
    public static function process_indexing_chunk(int $chunksize = 100) {
        $params = self::validate_parameters(self::process_indexing_chunk_parameters(), [
            'chunksize' => $chunksize,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/smartsearch:manage', $context);

        $result = \local_smartsearch\indexer::process_chunk((int) $params['chunksize']);

        return [
            'done' => !empty($result['done']),
            'in_progress' => !empty($result['in_progress']),
            'stats' => json_encode($result['stats'] ?? []),
            'timestamp' => (int) ($result['timestamp'] ?? time()),
        ];
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
     * Stop chunked indexing.
     *
     * @return array
     */
    public static function stop_indexing() {
        self::validate_parameters(self::stop_indexing_parameters(), []);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/smartsearch:manage', $context);

        \local_smartsearch\indexer::cancel_chunked_index();

        return ['status' => 'stopped'];
    }
}
