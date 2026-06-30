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
 * Base class for search areas.
 *
 * @package    local_smartsearch
 * @copyright  2025 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_smartsearch\search_area;


/**
 * Abstract base class for search areas.
 */
abstract class base {
    /**
     * Get the display name of this search area.
     *
     * @return string
     */
    abstract public function get_name(): string;

    /**
     * Get the recordset of items to index.
     *
     * @param int $modifiedfrom Timestamp to get items modified after
     * @return \moodle_recordset|null
     */
    abstract public function get_recordset(int $modifiedfrom = 0): ?\moodle_recordset;

    /**
     * Index a specific item.
     *
     * @param int $itemid The ID of the item to index
     * @param \stdClass|null $sourcerecord Optional source row from get_recordset()
     * @return array|null Array with keys: recordtype, recordid, title, subtitle, keywords, url, metadata
     */
    abstract public function index_item(int $itemid, ?\stdClass $sourcerecord = null): ?array;

    /**
     * Check whether the source record still exists (lightweight orphan cleanup).
     *
     * @param int $itemid Source record id
     * @return bool
     */
    abstract public function record_exists(int $itemid): bool;

    /**
     * Get the URL for a search result item.
     *
     * @param int $itemid The ID of the item
     * @return \moodle_url|null
     */
    abstract public function get_item_url(int $itemid): ?\moodle_url;

    /**
     * Get available quick actions for an item.
     *
     * @param int $itemid The ID of the item
     * @param int $userid The ID of the user performing the search
     * @return array Array of action arrays with keys: label, url, icon, capability
     */
    abstract public function get_item_actions(int $itemid, int $userid): array;

    /**
     * Check if a user can see/index this item.
     *
     * @param int $userid The ID of the user
     * @param int $itemid The ID of the item
     * @return bool
     */
    abstract public function can_index(int $userid, int $itemid): bool;

    /**
     * Get the indexing frequency for this area.
     *
     * @return string 'realtime' or 'cron'
     */
    abstract public function get_indexing_frequency(): string;

    /**
     * Get the record type for this search area.
     *
     * @return string
     */
    abstract public function get_record_type(): string;

    /**
     * Check if this search area is enabled.
     *
     * @return bool
     */
    public function is_enabled(): bool {
        // Map record types to config keys (using plural form as stored in settings).
        $configkeymap = [
            'user' => 'enable_users',
            'course' => 'enable_courses',
            'activity' => 'enable_activities',
            'setting' => 'enable_settings',
            'plugin' => 'enable_plugins',
            'category' => 'enable_categories',
        ];

        $recordtype = $this->get_record_type();
        $configkey = $configkeymap[$recordtype] ?? 'enable_' . $recordtype . 's';

        // Default to enabled if config doesn't exist (backward compatibility).
        $value = get_config('local_smartsearch', $configkey);
        if ($value === false) {
            return true; // Default enabled if not set.
        }

        return (bool) $value;
    }

    /**
     * Get the URL stored in the search index for an item.
     *
     * @param int $itemid Indexed record id
     * @return \moodle_url|null
     */
    protected function get_indexed_item_url(int $itemid): ?\moodle_url {
        global $DB;

        $record = $DB->get_record(
            'local_smartsearch_index',
            ['recordtype' => $this->get_record_type(), 'recordid' => $itemid],
            'url',
            IGNORE_MISSING
        );
        if (!$record || empty($record->url) || !is_string($record->url)) {
            return null;
        }

        return \local_smartsearch\query::parse_stored_url($record->url);
    }
}
