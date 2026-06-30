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
 * Indexer class for Smart Search.
 *
 * @package    local_smartsearch
 * @copyright  2025 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_smartsearch;


/**
 * Handles indexing logic for Smart Search.
 */
class indexer {
    /**
     * Index all items from all enabled search areas.
     *
     * @param int $modifiedfrom Timestamp to index items modified after
     * @param callable|null $progresscallback Optional callback for progress updates
     * @return array Statistics about the indexing process
     */
    public static function index_all(int $modifiedfrom = 0, ?callable $progresscallback = null): array {
        global $DB;

        // Check if table exists.
        $dbman = $DB->get_manager();
        $table = new \xmldb_table('local_smartsearch_index');
        if (!$dbman->table_exists($table)) {
            debugging('Smart Search index table does not exist. Please run database upgrade.', DEBUG_NORMAL);
            return [
                'total' => 0,
                'indexed' => 0,
                'skipped' => 0,
                'errors' => 1,
            ];
        }

        \local_smartsearch\category_path::reset_cache();
        \local_smartsearch\search_area\activity::reset_bulk_index_cache();

        $stats = [
            'total' => 0,
            'indexed' => 0,
            'skipped' => 0,
            'errors' => 0,
            'error_details' => [], // Store error details for debugging.
        ];

        $searchareas = self::get_enabled_search_areas();

        if (empty($searchareas)) {
            debugging('Smart Search: No enabled search areas found. Check plugin settings.', DEBUG_NORMAL);
            return $stats;
        }

        foreach ($searchareas as $area) {
            // Note: get_enabled_search_areas() already filters by is_enabled(),
            // but we check again here for safety.
            if (!$area->is_enabled()) {
                debugging('Smart Search: Skipping disabled area: ' . $area->get_record_type(), DEBUG_NORMAL);
                continue;
            }

            // Handle special cases for settings and plugin pages.
            if ($area instanceof \local_smartsearch\search_area\setting) {
                try {
                    $settings = $area->index_all_settings();
                    foreach ($settings as $settingdata) {
                        $stats['total']++;
                        try {
                            $itemdata = $area->index_setting(
                                $settingdata['setting'],
                                $settingdata['path'],
                                $settingdata['page'] ?? null
                            );
                            if ($itemdata) {
                                try {
                                    $added = self::add_to_index($itemdata);
                                    if ($added) {
                                        $stats['indexed']++;
                                    } else {
                                        $stats['errors']++;
                                        $errormsg = "Failed to add setting to index: "
                                            . ($settingdata['setting']->name ?? 'unknown');
                                        $stats['error_details'][] = [
                                            'type' => 'setting',
                                            'item' => $settingdata['setting']->name ?? 'unknown',
                                            'error' => 'Failed to add item to index (validation failed)',
                                        ];
                                        debugging($errormsg);
                                    }
                                } catch (\Exception $e) {
                                    $stats['errors']++;
                                    $errormsg = "Error adding setting to index: " . $e->getMessage();
                                    $stats['error_details'][] = [
                                        'type' => 'setting',
                                        'item' => $settingdata['setting']->name ?? 'unknown',
                                        'error' => $e->getMessage(),
                                    ];
                                    debugging($errormsg);
                                }
                            } else {
                                $stats['skipped']++;
                            }
                        } catch (\Exception $e) {
                            $stats['errors']++;
                            $errormsg = "Error indexing setting: " . $e->getMessage();
                            $stats['error_details'][] = [
                                'type' => 'setting',
                                'item' => $settingdata['setting']->name ?? 'unknown',
                                'error' => $e->getMessage(),
                            ];
                            debugging($errormsg);
                        }
                        if ($progresscallback) {
                            $progresscallback($stats);
                        }
                    }
                } catch (\Exception $e) {
                    $stats['errors']++;
                    $errormsg = "Error indexing settings: " . $e->getMessage();
                    debugging($errormsg);
                }
                continue;
            }

            if ($area instanceof \local_smartsearch\search_area\plugin) {
                try {
                    $pages = $area->index_all_plugin_pages();
                    foreach ($pages as $pagedata) {
                        $stats['total']++;
                        try {
                            $itemdata = $area->index_plugin_page($pagedata['node'], $pagedata['path']);
                            if ($itemdata) {
                                try {
                                    $added = self::add_to_index($itemdata);
                                    if ($added) {
                                        $stats['indexed']++;
                                    } else {
                                        $stats['errors']++;
                                        $errormsg = "Failed to add plugin page to index: " . ($pagedata['node']->name ?? 'unknown');
                                        $stats['error_details'][] = [
                                            'type' => 'plugin',
                                            'item' => $pagedata['node']->name ?? 'unknown',
                                            'error' => 'Failed to add item to index (validation failed)',
                                        ];
                                        debugging($errormsg);
                                    }
                                } catch (\Exception $e) {
                                    $stats['errors']++;
                                    $errormsg = "Error adding plugin page to index: " . $e->getMessage();
                                    $stats['error_details'][] = [
                                        'type' => 'plugin',
                                        'item' => $pagedata['node']->name ?? 'unknown',
                                        'error' => $e->getMessage(),
                                    ];
                                    debugging($errormsg);
                                }
                            } else {
                                $stats['skipped']++;
                            }
                        } catch (\Exception $e) {
                            $stats['errors']++;
                            $errormsg = "Error indexing plugin page: " . $e->getMessage();
                            $stats['error_details'][] = [
                                'type' => 'plugin',
                                'item' => $pagedata['node']->name ?? 'unknown',
                                'error' => $e->getMessage(),
                            ];
                            debugging($errormsg);
                        }
                        if ($progresscallback) {
                            $progresscallback($stats);
                        }
                    }
                } catch (\Exception $e) {
                    $stats['errors']++;
                    $errormsg = "Error indexing plugin pages: " . $e->getMessage();
                    debugging($errormsg);
                }
                continue;
            }

            // Standard recordset-based indexing.
            $recordset = $area->get_recordset($modifiedfrom);
            if (!$recordset) {
                continue;
            }

            $bulkuserindex = $area instanceof \local_smartsearch\search_area\user;
            if ($bulkuserindex) {
                \local_smartsearch\search_area\user::begin_bulk_index();
            } else if ($area instanceof \local_smartsearch\search_area\activity) {
                \local_smartsearch\search_area\activity::reset_bulk_index_cache();
            }

            $existingindex = [];
            $existingrecords = $DB->get_records(
                'local_smartsearch_index',
                ['recordtype' => $area->get_record_type()],
                '',
                'recordid, id'
            );
            foreach ($existingrecords as $existingrecord) {
                $existingindex[(int) $existingrecord->recordid] = (int) $existingrecord->id;
            }

            foreach ($recordset as $record) {
                $stats['total']++;
                try {
                    $itemdata = $area->index_item((int) $record->id, $record);
                    if ($itemdata) {
                        $added = self::add_to_index($itemdata, $existingindex);
                        if ($added) {
                            $stats['indexed']++;
                        } else {
                            // If add_to_index returns false, it's an error (not a skip).
                            $stats['errors']++;
                            $stats['error_details'][] = [
                                'type' => $area->get_record_type(),
                                'item' => $record->id,
                                'error' => 'Failed to add item to index (check error logs)',
                            ];
                        }
                    } else {
                        $stats['skipped']++;
                    }
                } catch (\Exception $e) {
                    $stats['errors']++;
                    $errormsg = "Error indexing item {$record->id} in area " . $area->get_name() . ": " . $e->getMessage();
                    $stats['error_details'][] = [
                        'type' => $area->get_record_type(),
                        'item' => $record->id,
                        'error' => $e->getMessage(),
                    ];
                    debugging($errormsg);
                    // Log to error log for admin visibility.
                }

                if ($progresscallback) {
                    $progresscallback($stats);
                }
            }
            $recordset->close();

            if ($bulkuserindex) {
                \local_smartsearch\search_area\user::end_bulk_index();
            }
        }

        // Clean up orphaned entries.
        self::cleanup_orphaned_entries();

        return $stats;
    }

    /**
     * Index a single item.
     *
     * @param string $recordtype The type of record
     * @param int $recordid The ID of the record
     * @return bool Success
     */
    public static function index_item(string $recordtype, int $recordid): bool {
        $area = self::get_search_area_by_type($recordtype);
        if (!$area || !$area->is_enabled()) {
            return false;
        }

        try {
            $itemdata = $area->index_item($recordid);
            if ($itemdata) {
                $existingindex = [];
                self::add_to_index($itemdata, $existingindex);
                return true;
            }
        } catch (\Exception $e) {
            debugging("Error indexing item {$recordid} of type {$recordtype}: " . $e->getMessage());
        }

        return false;
    }

    /**
     * Remove an item from the index.
     *
     * @param string $recordtype The type of record
     * @param int $recordid The ID of the record
     * @return bool Success
     */
    public static function remove_from_index(string $recordtype, int $recordid): bool {
        global $DB;

        return $DB->delete_records('local_smartsearch_index', [
            'recordtype' => $recordtype,
            'recordid' => $recordid,
        ]);
    }

    /**
     * Add or update an item in the index.
     *
     * @param array $itemdata Array with keys: recordtype, recordid, title, subtitle, keywords, url, metadata
     * @param array $existingindex Map of recordid => index row id (updated in place)
     * @return bool Success
     */
    protected static function add_to_index(array $itemdata, array &$existingindex = []): bool {
        global $DB;

        try {
            // Validate required fields.
            // Note: recordid can be 0 (crc32 can return 0), so we check isset() and !== '' instead of empty().
            $missingrecordtype = empty($itemdata['recordtype']);
            $missingrecordid = !isset($itemdata['recordid']) || $itemdata['recordid'] === '';
            $missingtitle = empty($itemdata['title']);

            if ($missingrecordtype || $missingrecordid || $missingtitle) {
                $recordtype = $itemdata['recordtype'] ?? 'missing';
                $recordid = $itemdata['recordid'] ?? 'missing';
                $title = $itemdata['title'] ?? 'missing';
                $errormsg = 'Smart Search: Missing required fields in itemdata. ' .
                    'recordtype: ' . $recordtype . ', ' .
                    'recordid: ' . $recordid . ', ' .
                    'title: ' . $title;
                debugging($errormsg, DEBUG_NORMAL);
                return false;
            }

            // Ensure URL is a string (could be a moodle_url object or null).
            $url = $itemdata['url'] ?? '';
            if (is_object($url) && method_exists($url, 'out')) {
                $url = $url->out(false);
            } else if ($url === null) {
                // If URL is null, use a default fallback.
                $url = '/';
            } else if (!is_string($url)) {
                $url = (string) $url;
            }

            // Normalize URL to ensure it's a relative path (not a full URL).
            // This ensures URLs work across different environments (local, staging, production).
            global $CFG;

            // If URL is a full URL (starts with http:// or https://), extract just the path.
            if (preg_match('#^https?://[^/]+(.*)$#', $url, $matches)) {
                $url = $matches[1];
            }

            // Remove wwwroot prefix if present (handles cases where out(false) still includes wwwroot).
            // Use parse_url to extract just the path component, which is more reliable.
            if (!empty($CFG->wwwroot)) {
                // Parse wwwroot to get its path component.
                $wwwrootparts = parse_url($CFG->wwwroot);
                $wwwrootpath = $wwwrootparts['path'] ?? '';

                // Remove wwwroot path prefix if present.
                if (!empty($wwwrootpath) && strpos($url, $wwwrootpath) === 0) {
                    $url = substr($url, strlen($wwwrootpath));
                }

                // Also check if full wwwroot is embedded in the URL.
                if (strpos($url, $CFG->wwwroot) === 0) {
                    $url = substr($url, strlen($CFG->wwwroot));
                }
            }

            // Ensure path starts with / for relative URLs.
            if (!empty($url) && $url[0] !== '/') {
                $url = '/' . $url;
            }

            // If URL is empty after normalization, set to root.
            if (empty($url) || $url === '/') {
                $url = '/';
            }

            // Ensure URL is not empty.
            if (empty($url)) {
                $url = '/';
            }

            $record = (object) [
                'recordtype' => $itemdata['recordtype'],
                'recordid' => (int) $itemdata['recordid'],
                'contextpath' => $itemdata['contextpath'] ?? '',
                'title' => substr($itemdata['title'], 0, 255), // Ensure it fits in char(255).
                'subtitle' => isset($itemdata['subtitle'])
                    ? substr($itemdata['subtitle'], 0, 1000)
                    : '', // Ensure it fits in char(1000).
                'keywords' => is_array($itemdata['keywords'])
                    ? json_encode($itemdata['keywords'])
                    : ($itemdata['keywords'] ?? ''),
                'url' => $url,
                'metadata' => is_array($itemdata['metadata']) ? json_encode($itemdata['metadata']) : ($itemdata['metadata'] ?? ''),
                'updatedat' => time(),
                'relevance_score' => 0,
            ];

            $recordid = (int) $record->recordid;
            $existingid = $existingindex[$recordid] ?? null;

            if ($existingid) {
                $record->id = $existingid;
                $result = $DB->update_record('local_smartsearch_index', $record);
                if (!$result) {
                    debugging(
                        "Smart Search: Failed to update record: recordtype={$record->recordtype}, "
                        . "recordid={$record->recordid}",
                        DEBUG_NORMAL
                    );
                }
                return $result;
            }

            $newid = $DB->insert_record('local_smartsearch_index', $record);
            if ($newid === false) {
                $titleshort = substr($record->title, 0, 50);
                debugging("Smart Search: Failed to insert record: recordtype={$record->recordtype}, " .
                    "recordid={$record->recordid}, title={$titleshort}", DEBUG_NORMAL);
                return false;
            }
            $existingindex[$recordid] = (int) $newid;
            return true;
        } catch (\dml_exception $e) {
            $errormsg = 'Smart Search: Error adding to index: ' . $e->getMessage();
            debugging($errormsg, DEBUG_NORMAL);
            throw $e;
        } catch (\Exception $e) {
            debugging('Smart Search: Unexpected error adding to index: ' . $e->getMessage(), DEBUG_NORMAL);
            return false;
        }
    }

    /**
     * Clean up orphaned index entries (items that no longer exist).
     *
     * @return int Number of entries cleaned up
     */
    protected static function cleanup_orphaned_entries(): int {
        global $DB;

        // Check if table exists.
        $dbman = $DB->get_manager();
        $table = new \xmldb_table('local_smartsearch_index');
        if (!$dbman->table_exists($table)) {
            return 0;
        }

        $cleaned = 0;
        $searchareas = self::get_enabled_search_areas();

        foreach ($searchareas as $area) {
            try {
                $recordtype = $area->get_record_type();

                // Skip cleanup for settings and plugin pages - they use hash-based IDs
                // and don't have a numeric ID that can be checked via index_item().
                if (
                    $area instanceof \local_smartsearch\search_area\setting ||
                    $area instanceof \local_smartsearch\search_area\plugin
                ) {
                    continue;
                }

                $indexeditems = $DB->get_records('local_smartsearch_index', ['recordtype' => $recordtype], '', 'DISTINCT recordid');

                foreach ($indexeditems as $indexed) {
                    try {
                        if (!$area->record_exists((int) $indexed->recordid)) {
                            self::remove_from_index($recordtype, (int) $indexed->recordid);
                            $cleaned++;
                        }
                    } catch (\Exception $e) {
                        // Skip items that cause errors during cleanup.
                        debugging('Smart Search: Error checking item during cleanup: ' . $e->getMessage(), DEBUG_NORMAL);
                    }
                }
            } catch (\Exception $e) {
                debugging('Smart Search: Error during cleanup for area: ' . $e->getMessage(), DEBUG_NORMAL);
            }
        }

        return $cleaned;
    }

    /**
     * Get all enabled search areas.
     *
     * @return array Array of search_area\base instances
     */
    protected static function get_enabled_search_areas(): array {
        $areas = [];
        $areaclasses = [
            'user' => \local_smartsearch\search_area\user::class,
            'course' => \local_smartsearch\search_area\course::class,
            'activity' => \local_smartsearch\search_area\activity::class,
            'setting' => \local_smartsearch\search_area\setting::class,
            'plugin' => \local_smartsearch\search_area\plugin::class,
            'category' => \local_smartsearch\search_area\category::class,
        ];

        foreach ($areaclasses as $type => $class) {
            if (class_exists($class)) {
                $area = new $class();
                $isenabled = $area->is_enabled();
                $msg = "Smart Search: Area '$type' is " . ($isenabled ? 'enabled' : 'disabled');
                debugging($msg);
                if ($isenabled) {
                    $areas[] = $area;
                }
            }
        }

        // Also include registered plugin areas.
        $registered = \local_smartsearch\api::get_registered_areas();
        foreach ($registered as $registeredarea) {
            if ($registeredarea->is_enabled()) {
                $areas[] = $registeredarea;
            }
        }

        return $areas;
    }

    /**
     * Get a search area by record type.
     *
     * @param string $recordtype The record type
     * @return search_area\base|null
     */
    protected static function get_search_area_by_type(string $recordtype): ?search_area\base {
        $areaclasses = [
            'user' => \local_smartsearch\search_area\user::class,
            'course' => \local_smartsearch\search_area\course::class,
            'activity' => \local_smartsearch\search_area\activity::class,
            'setting' => \local_smartsearch\search_area\setting::class,
            'plugin' => \local_smartsearch\search_area\plugin::class,
            'category' => \local_smartsearch\search_area\category::class,
        ];

        if (isset($areaclasses[$recordtype]) && class_exists($areaclasses[$recordtype])) {
            return new $areaclasses[$recordtype]();
        }

        // Check registered areas.
        $registered = \local_smartsearch\api::get_registered_areas();
        foreach ($registered as $area) {
            if ($area->get_record_type() === $recordtype) {
                return $area;
            }
        }

        return null;
    }
}
