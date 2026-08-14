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
 * @copyright  2026 Mooplugins
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

            if (!$indexexists && $DB->get_dbfamily() === 'mysql') {
                // Optional MySQL/MariaDB FULLTEXT index. Search uses LIKE on all engines.
                $sql = "CREATE FULLTEXT INDEX idx_fulltext ON {local_smartsearch_index} (title, subtitle)";
                try {
                    $DB->execute($sql);
                } catch (\Exception $e) {
                    debugging("Could not create FULLTEXT index: " . $e->getMessage());
                }
            }
        }

        upgrade_plugin_savepoint(true, 2024010100, 'local', 'smartsearch');
    }

    if ($oldversion < 2026081400) {
        $table = new \xmldb_table('local_smartsearch_index');
        if ($dbman->table_exists($table)) {
            // Keep one row per recordtype+recordid (lowest id).
            $duplicates = $DB->get_recordset_sql(
                "SELECT recordtype, recordid, MIN(id) AS keepid
                   FROM {local_smartsearch_index}
               GROUP BY recordtype, recordid
                 HAVING COUNT(*) > 1"
            );
            foreach ($duplicates as $duplicate) {
                $DB->delete_records_select(
                    'local_smartsearch_index',
                    'recordtype = :recordtype AND recordid = :recordid AND id <> :keepid',
                    [
                        'recordtype' => $duplicate->recordtype,
                        'recordid' => $duplicate->recordid,
                        'keepid' => $duplicate->keepid,
                    ]
                );
            }
            $duplicates->close();

            $oldindex = new \xmldb_index('idx_recordtype', XMLDB_INDEX_NOTUNIQUE, ['recordtype', 'recordid']);
            if ($dbman->index_exists($table, $oldindex)) {
                $dbman->drop_index($table, $oldindex);
            }

            $uniqueindex = new \xmldb_index('idx_recordtype', XMLDB_INDEX_UNIQUE, ['recordtype', 'recordid']);
            if (!$dbman->index_exists($table, $uniqueindex)) {
                $dbman->add_index($table, $uniqueindex);
            }
        }

        upgrade_plugin_savepoint(true, 2026081400, 'local', 'smartsearch');
    }

    if ($oldversion < 2026081402) {
        upgrade_plugin_savepoint(true, 2026081402, 'local', 'smartsearch');
    }

    if ($oldversion < 2026081403) {
        upgrade_plugin_savepoint(true, 2026081403, 'local', 'smartsearch');
    }

    return true;
}
