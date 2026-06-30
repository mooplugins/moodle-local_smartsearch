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
 * Settings search area.
 *
 * @package    local_smartsearch
 * @copyright  2025 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_smartsearch\search_area;


/**
 * Settings search area implementation.
 */
class setting extends base {
    /**
     * Get the display name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('category_settings', 'local_smartsearch');
    }

    /**
     * Get the recordset of settings to index.
     * Note: Settings don't have a traditional recordset, so we return a custom structure.
     *
     * @param int $modifiedfrom Timestamp
     * @return \moodle_recordset|null
     */
    public function get_recordset(int $modifiedfrom = 0): ?\moodle_recordset {
        // Settings are indexed from the admin tree, not a database table.
        // We'll return null here and handle indexing differently.
        return null;
    }

    /**
     * Index all settings from the admin tree.
     * This is called separately during indexing.
     *
     * @return array Array of setting data arrays
     */
    public function index_all_settings(): array {
        global $CFG;
        $settings = [];

        // Ensure admin tree is fully loaded.
        require_once($CFG->libdir . '/adminlib.php');
        // Force reload and require full tree (all settings).
        // Note: admin_get_root() returns the global $ADMIN and ensures it's loaded.
        try {
            $adminroot = admin_get_root(true, true);
            $msg = "Smart Search Settings: Admin root loaded: " . ($adminroot ? 'yes' : 'no');
            debugging($msg);

            // Traverse admin tree.
            if ($adminroot) {
                $this->traverse_admin_tree($adminroot, $settings, []);
                $msg = "Smart Search Settings: Traversed admin tree, found " . count($settings) . " settings";
                debugging($msg);
            } else {
                $msg = "Smart Search Settings: Admin root is null";
                debugging($msg);
            }
        } catch (\Exception $e) {
            // Log error but don't fail completely.
            debugging("Error loading admin tree for settings: " . $e->getMessage(), DEBUG_NORMAL);
        }

        return $settings;
    }

    /**
     * Traverse the admin tree recursively.
     *
     * @param object $node Current node
     * @param array $settings Array to populate
     * @param array $path Current path
     * @return void
     */
    protected function traverse_admin_tree($node, array &$settings, array $path): void {
        if (isset($node->name)) {
            $path[] = $node->name;
        }

        // Get settings from this node first (before traversing children).
        // Only admin_settingpage objects have settings.
        // admin_settingpage->settings is a stdClass object where each setting is a property.
        if ($node instanceof \admin_settingpage && isset($node->settings) && is_object($node->settings)) {
            // Iterate through settings (they're stored as object properties).
            foreach ($node->settings as $setting) {
                if (is_object($setting) && isset($setting->name)) {
                    // Skip settings that don't actually save anything (like headings/descriptions).
                    if (empty($setting->nosave)) {
                        // Ensure setting has page reference (might not be set automatically).
                        if (!isset($setting->page)) {
                            $setting->page = $node;
                        }
                        $settings[] = [
                            'setting' => $setting,
                            'path' => $path,
                            'page' => $node, // Pass the page explicitly.
                        ];
                    }
                }
            }
        }

        // Traverse children using get_children() method if available (for admin_category).
        $children = null;
        if (method_exists($node, 'get_children')) {
            $children = $node->get_children();
        } else if (isset($node->children)) {
            $children = $node->children;
        }

        if ($children && is_array($children)) {
            foreach ($children as $child) {
                $this->traverse_admin_tree($child, $settings, $path);
            }
        }
    }

    /**
     * Index a setting.
     *
     * @param int $itemid Setting ID (not used, settings are identified by name/path)
     * @param \stdClass|null $sourcerecord Unused for settings
     * @return array|null
     */
    public function index_item(int $itemid, ?\stdClass $sourcerecord = null): ?array {
        // Settings don't have numeric IDs, so this method is not used.
        // Use index_all_settings() instead.
        return null;
    }

    /**
     * Index a specific setting.
     *
     * @param object $setting The setting object
     * @param array $path The path to the setting
     * @param object|null $page The admin_settingpage object (optional, will use $setting->page if available)
     * @return array
     */
    public function index_setting($setting, array $path, $page = null): array {
        $pathstring = implode(' › ', $path);
        $name = $setting->name ?? '';

        // Handle visiblename - it might be a lang_string object or empty.
        $visiblename = $setting->visiblename ?? $name;
        if ($visiblename instanceof \lang_string) {
            $visiblename = $visiblename->out();
        }
        // Convert to string and trim whitespace.
        $visiblename = trim((string) $visiblename);
        if (empty($visiblename)) {
            $visiblename = trim((string) $name);
        }
        if (empty($visiblename)) {
            // Skip settings without a name or visiblename.
            return null;
        }

        $keywords = [$name, $visiblename];
        if (isset($setting->description)) {
            $keywords[] = strip_tags($setting->description);
        }

        // Generate URL for settings page.
        // Settings are on admin_settingpage objects, which have get_settings_page_url() method.
        $url = '/admin/settings.php';

        // Get page from parameter, setting->page, or try to find it from path.
        $settingspage = $page ?? ($setting->page ?? null);

        if ($settingspage instanceof \admin_settingpage) {
            // Use get_settings_page_url() method which returns /admin/settings.php?section=name.
            try {
                $urlobj = $settingspage->get_settings_page_url();
                $url = $urlobj->out(false);
            } catch (\Exception $e) {
                // Fallback: build URL manually if page has a name.
                if (isset($settingspage->name) && !empty($settingspage->name)) {
                    $urlobj = new \moodle_url('/admin/settings.php', ['section' => $settingspage->name]);
                    $url = $urlobj->out(false);
                }
            }
        } else if (isset($settingspage) && method_exists($settingspage, 'get_settings_page_url')) {
            // Fallback for other page types.
            try {
                $urlobj = $settingspage->get_settings_page_url();
                $url = $urlobj->out(false);
            } catch (\Exception $e) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
                // Ignore and use default.
            }
        } else if (!empty($path)) {
            // Last resort: use the last path segment as the section name.
            // The last segment in the path is typically the settings page name.
            $lastpath = end($path);
            if (!empty($lastpath)) {
                $urlobj = new \moodle_url('/admin/settings.php', ['section' => $lastpath]);
                $url = $urlobj->out(false);
            }
        }

        // Use hash of path and name as unique identifier.
        // crc32 can return negative numbers on 32-bit systems, so we use abs() and ensure it's never 0.
        $hash = crc32($pathstring . '|' . $name);
        $uniqueid = abs($hash);
        if ($uniqueid === 0) {
            // If hash is 0, use a hash of just the name to ensure uniqueness.
            $uniqueid = abs(crc32($name)) ?: 1;
        }

        if (!\local_smartsearch\permissions::is_usable_result_url($url)) {
            return null;
        }

        return [
            'recordtype' => 'setting',
            'recordid' => $uniqueid, // Use hash as unique identifier.
            'contextpath' => $pathstring,
            'title' => $visiblename,
            'subtitle' => $pathstring . (isset($setting->description) ? ' | ' . strip_tags($setting->description) : ''),
            'keywords' => $keywords,
            'url' => $url,
            'metadata' => [
                'name' => $name,
                'path' => $path,
            ],
        ];
    }

    /**
     * Get setting URL.
     *
     * @param int $itemid Not used for settings
     * @return \moodle_url|null
     */
    public function get_item_url(int $itemid): ?\moodle_url {
        return $this->get_indexed_item_url($itemid);
    }

    /**
     * Get setting actions.
     *
     * @param int $itemid Setting hash id
     * @param int $userid Current user ID
     * @return array
     */
    public function get_item_actions(int $itemid, int $userid): array {
        $url = $this->get_indexed_item_url($itemid);
        if (!$url) {
            return [];
        }

        return [
            [
                'label' => get_string('action_view', 'local_smartsearch'),
                'url' => $url,
                'icon' => 'cog',
            ],
        ];
    }

    /**
     * Check if setting can be indexed/viewed.
     *
     * @param int $userid Current user ID
     * @param int $itemid Not used for settings
     * @return bool
     */
    public function can_index(int $userid, int $itemid): bool {
        return \local_smartsearch\permissions::can_access_settings($userid);
    }

    /**
     * Get indexing frequency.
     *
     * @return string
     */
    public function get_indexing_frequency(): string {
        return 'cron';
    }

    /**
     * Get record type.
     *
     * @return string
     */
    public function get_record_type(): string {
        return 'setting';
    }

    /**
     * Settings use hash ids; orphan cleanup is handled separately.
     *
     * @param int $itemid Hash-based record id
     * @return bool
     */
    public function record_exists(int $itemid): bool {
        return true;
    }
}
