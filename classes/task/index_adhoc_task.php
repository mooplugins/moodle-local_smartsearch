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
 * Ad-hoc full reindex task for Smart Search.
 *
 * @package    local_smartsearch
 * @copyright  2026 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_smartsearch\task;

/**
 * Runs a full reindex in the background after "Index now".
 */
class index_adhoc_task extends \core\task\adhoc_task {
    /**
     * Execute full indexing with progress heartbeats.
     */
    public function execute() {
        $starttime = time();
        $lastupdatetime = 0.0;
        $updateinterval = 1.0;

        try {
            $stats = \local_smartsearch\indexer::index_all(
                0,
                function ($stats) use (&$lastupdatetime, $updateinterval, $starttime) {
                    // Allow stop requests to abort between progress updates.
                    if (empty(get_config('local_smartsearch', 'indexing_in_progress'))) {
                        throw new \local_smartsearch\exception\indexing_cancelled_exception();
                    }

                    $now = microtime(true);
                    if (($now - $lastupdatetime) < $updateinterval) {
                        return;
                    }

                    $stats = array_merge([
                        'total' => 0,
                        'indexed' => 0,
                        'skipped' => 0,
                        'errors' => 0,
                    ], $stats);
                    $stats['elapsed'] = time() - $starttime;
                    $stats['last_update'] = time();

                    set_config('indexing_stats', json_encode($stats), 'local_smartsearch');
                    $lastupdatetime = $now;
                }
            );

            set_config('indexing_stats', json_encode($stats), 'local_smartsearch');
            set_config('last_index_time', time(), 'local_smartsearch');

            if (!empty($stats['error_details'])) {
                set_config('indexing_error_details', json_encode($stats['error_details']), 'local_smartsearch');
            } else {
                unset_config('indexing_error_details', 'local_smartsearch');
            }
        } catch (\local_smartsearch\exception\indexing_cancelled_exception $e) {
            // Stop requested via external API; leave partial stats as-is.
            debugging('Smart Search indexing cancelled by stop request.', DEBUG_NORMAL);
        } catch (\Throwable $e) {
            debugging('Smart Search indexing error: ' . $e->getMessage(), DEBUG_NORMAL);
            debugging('Stack trace: ' . $e->getTraceAsString(), DEBUG_DEVELOPER);
        } finally {
            unset_config('indexing_in_progress', 'local_smartsearch');
        }
    }
}
