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
 * Upgrade script for Smart Search.
 *
 * @package    local_smartsearch
 * @copyright  2025 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Upgrade the local_smartsearch plugin.
 *
 * @param int $oldversion The old version of the plugin.
 * @return bool
 */
function xmldb_local_smartsearch_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2024010100) {
        // Create FULLTEXT index manually (XMLDB doesn't support FULLTEXT well).
        // Check if table exists first.
        $table = new \xmldb_table('local_smartsearch_index');
        if ($dbman->table_exists($table)) {
            // Check if index already exists.
            $indexexists = false;
            try {
                $indexes = $DB->get_indexes('local_smartsearch_index');
                foreach ($indexes as $indexname => $index) {
                    if (
                        $indexname === 'idx_fulltext'
                        || (isset($index['columns'])
                            && in_array('title', $index['columns'])
                            && in_array('subtitle', $index['columns']))
                    ) {
                        $indexexists = true;
                        break;
                    }
                }
            } catch (\Exception $e) {
                // Table might not be ready yet, will try again on next upgrade.
                debugging("Could not check indexes: " . $e->getMessage());
            }

            if (!$indexexists) {
                // Create FULLTEXT index using raw SQL.
                $sql = "CREATE FULLTEXT INDEX idx_fulltext ON {local_smartsearch_index} (title, subtitle)";
                try {
                    $DB->execute($sql);
                } catch (\Exception $e) {
                    // Index might already exist or there might be an issue.
                    debugging("Could not create FULLTEXT index: " . $e->getMessage());
                }
            }
        }

        upgrade_plugin_savepoint(true, 2024010100, 'local', 'smartsearch');
    }

    return true;
}
