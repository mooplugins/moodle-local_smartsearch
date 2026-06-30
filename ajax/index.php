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
 * AJAX endpoint for Smart Search indexing.
 *
 * @package    local_smartsearch
 * @copyright  2025 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_login();

// Check capability.
require_capability('local/smartsearch:manage', \context_system::instance());

// Moodle automatically sets Content-Type header for AJAX_SCRIPT, but ensure it's set.
if (!headers_sent()) {
    @header('Content-Type: application/json; charset=utf-8');
}

$response = [];
$httpcode = 200;
$action = '';
$shouldrunindexing = false;

try {
    $action = optional_param('action', '', PARAM_ALPHA);

    if (empty($action)) {
        $httpcode = 400;
        $response = ['error' => get_string('error_missing_action', 'local_smartsearch')];
    } else if ($action === 'start') {
        require_sesskey();

        // Check if indexing is already in progress.
        $inprogress = get_config('local_smartsearch', 'indexing_in_progress');
        if (!empty($inprogress)) {
            $response = ['status' => 'already_running'];
        } else {
            // Store indexing start time and status.
            set_config('indexing_in_progress', time(), 'local_smartsearch');
            set_config('indexing_stats', json_encode([
            'total' => 0,
            'indexed' => 0,
            'skipped' => 0,
            'errors' => 0,
            ]), 'local_smartsearch');

            $response = ['status' => 'started'];
            $shouldrunindexing = true;

            // Use ignore_user_abort to continue even if client disconnects.
            ignore_user_abort(true);
        }
    } else if ($action === 'progress') {
        // No sesskey required for progress checks.
        $statsjson = get_config('local_smartsearch', 'indexing_stats');
        $inprogress = get_config('local_smartsearch', 'indexing_in_progress');

        $stats = null;
        if (!empty($statsjson)) {
            $decoded = @json_decode($statsjson, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $stats = $decoded;
            }
        }

        // Ensure stats has at least default values if indexing is in progress.
        if (!empty($inprogress) && $stats === null) {
            $stats = [
                'total' => 0,
                'indexed' => 0,
                'skipped' => 0,
                'errors' => 0,
            ];
        }

        // Add timestamp to response for debugging and tracking.
        $response = [
            'in_progress' => !empty($inprogress),
            'stats' => $stats,
            'timestamp' => time(),
        ];
    } else if ($action === 'stop') {
        require_sesskey();
        unset_config('indexing_in_progress', 'local_smartsearch');
        $response = ['status' => 'stopped'];
    } else {
        $httpcode = 400;
        $response = ['error' => get_string('error_invalid_action', 'local_smartsearch')];
    }
} catch (\moodle_exception $e) {
    $httpcode = 500;
    $response = [
        'error' => get_string('error_server', 'local_smartsearch'),
        'message' => $e->getMessage(),
        'errorcode' => $e->errorcode ?? null,
    ];
} catch (\Exception $e) {
    $httpcode = 500;
    $response = [
        'error' => get_string('error_server', 'local_smartsearch'),
        'message' => $e->getMessage(),
    ];
} catch (\Throwable $e) {
    $httpcode = 500;
    $response = [
        'error' => get_string('error_server', 'local_smartsearch'),
        'message' => $e->getMessage(),
    ];
}

// Set HTTP response code if not already sent.
if (!headers_sent() && $httpcode !== 200) {
    http_response_code($httpcode);
}

// Output JSON response first.
echo json_encode($response, JSON_UNESCAPED_SLASHES);

// Flush output so client gets response immediately.
if ($shouldrunindexing) {
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        // For non-FastCGI, flush output buffers.
        if (ob_get_level()) {
            ob_end_flush();
        }
        flush();
    }

    // Now run indexing in background.
    try {
        // Load required class.
        require_once(__DIR__ . '/../classes/indexer.php');

        // Track last update time to throttle progress updates.
        $lastupdatetime = 0;
        $updateinterval = 1.0; // Update every 1 second for progress tracking.
        $starttime = time();

        $stats = \local_smartsearch\indexer::index_all(0, function ($stats) use (&$lastupdatetime, $updateinterval, $starttime) {
            // Throttle progress updates to avoid too many database writes.
            // Send heartbeat every 1 second to track progress.
            $now = microtime(true);
            if (($now - $lastupdatetime) >= $updateinterval) {
                // Ensure stats array has all required keys.
                $stats = array_merge([
                    'total' => 0,
                    'indexed' => 0,
                    'skipped' => 0,
                    'errors' => 0,
                ], $stats);

                // Add elapsed time and timestamp for better progress tracking.
                $stats['elapsed'] = time() - $starttime;
                $stats['last_update'] = time();

                // Save progress to config - this is the heartbeat.
                set_config('indexing_stats', json_encode($stats), 'local_smartsearch');
                $lastupdatetime = $now;
            }
        });

        // Update final stats.
        set_config('indexing_stats', json_encode($stats), 'local_smartsearch');
        set_config('last_index_time', time(), 'local_smartsearch');
        unset_config('indexing_in_progress', 'local_smartsearch');

        // Store error details if any.
        if (!empty($stats['error_details'])) {
            set_config('indexing_error_details', json_encode($stats['error_details']), 'local_smartsearch');
        } else {
            unset_config('indexing_error_details', 'local_smartsearch');
        }
    } catch (\Exception $e) {
        unset_config('indexing_in_progress', 'local_smartsearch');
        debugging('Smart Search indexing error: ' . $e->getMessage(), DEBUG_NORMAL);
        debugging('Stack trace: ' . $e->getTraceAsString(), DEBUG_DEVELOPER);
    } catch (\Throwable $e) {
        unset_config('indexing_in_progress', 'local_smartsearch');
        $errormsg = 'Smart Search indexing fatal error: ' . $e->getMessage();
        debugging($errormsg, DEBUG_NORMAL);
        debugging('Stack trace: ' . $e->getTraceAsString(), DEBUG_DEVELOPER);
    }
}
