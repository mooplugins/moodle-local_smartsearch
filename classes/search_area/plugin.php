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
 * Plugin pages search area.
 *
 * @package    local_smartsearch
 * @copyright  2025 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_smartsearch\search_area;


/**
 * Plugin pages search area implementation.
 */
class plugin extends base {
    /**
     * Get the display name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('category_plugins', 'local_smartsearch');
    }

    /**
     * Get the recordset of plugin pages to index.
     * Note: Plugin pages are indexed from navigation/admin tree.
     *
     * @param int $modifiedfrom Timestamp
     * @return \moodle_recordset|null
     */
    public function get_recordset(int $modifiedfrom = 0): ?\moodle_recordset {
        // Plugin pages are indexed from navigation, not a database table.
        return null;
    }

    /**
     * Index all plugin pages.
     *
     * @return array Array of plugin page data
     */
    public function index_all_plugin_pages(): array {
        global $CFG;
        $pages = [];

        // Ensure admin tree is fully loaded.
        require_once($CFG->libdir . '/adminlib.php');
        // Force reload and require full tree (all pages, not just settings).
        // Note: admin_get_root() returns the global $ADMIN and ensures it's loaded.
        try {
            $adminroot = admin_get_root(true, true);
            $msg = "Smart Search Plugin: Admin root loaded: " . ($adminroot ? 'yes' : 'no');
            debugging($msg);

            // Traverse admin tree for plugin pages.
            if ($adminroot) {
                $this->traverse_admin_tree($adminroot, $pages, []);
                $msg = "Smart Search Plugin: Traversed admin tree, found " . count($pages) . " pages";
                debugging($msg);
            } else {
                $msg = "Smart Search Plugin: Admin root is null";
                debugging($msg);
            }
        } catch (\Exception $e) {
            // Log error but don't fail completely.
            debugging("Error loading admin tree for plugin pages: " . $e->getMessage(), DEBUG_NORMAL);
        }

        return $pages;
    }

    /**
     * Traverse the admin tree for plugin pages.
     *
     * @param object $node Current node
     * @param array $pages Array to populate
     * @param array $path Current path
     * @return void
     */
    protected function traverse_admin_tree($node, array &$pages, array $path): void {
        if (isset($node->name)) {
            $path[] = $node->name;
        }

        // Index all nodes with names except root.
        // This includes:
        // - External pages (admin_externalpage) - have URLs
        // - Categories (admin_category) - accessible via category.php
        // - Tool pages - have URLs.
        if (isset($node->name) && !empty($node->name) && $node->name !== 'root') {
            $pages[] = [
                'node' => $node,
                'path' => $path,
            ];
        }

        // Traverse children using get_children() method if available (for admin_category).
        $children = null;
        if (method_exists($node, 'get_children')) {
            try {
                $children = $node->get_children();
            } catch (\Exception $e) {
                debugging("Smart Search Plugin: Error getting children: " . $e->getMessage());
                $children = null;
            }
        } else if (isset($node->children)) {
            $children = $node->children;
        }

        if ($children && is_array($children)) {
            foreach ($children as $child) {
                if ($child) {
                    $this->traverse_admin_tree($child, $pages, $path);
                }
            }
        }
    }

    /**
     * Index a plugin page.
     *
     * @param int $itemid Not used for plugin pages
     * @param \stdClass|null $sourcerecord Unused for plugin pages
     * @return array|null
     */
    public function index_item(int $itemid, ?\stdClass $sourcerecord = null): ?array {
        // Plugin pages don't have numeric IDs.
        return null;
    }

    /**
     * Index a specific plugin page.
     *
     * @param object $node The admin node
     * @param array $path The path to the page
     * @return array|null
     */
    public function index_plugin_page($node, array $path): ?array {
        $pathstring = implode(' › ', $path);
        $name = $node->name ?? '';
        $visiblename = $node->visiblename ?? $name;

        // Use hash of path and name as unique identifier.
        // crc32 can return negative numbers on 32-bit systems, so we use abs() and ensure it's never 0.
        $hash = crc32($pathstring . '|' . $name);
        $uniqueid = abs($hash);
        if ($uniqueid === 0) {
            // If hash is 0, use a hash of just the name to ensure uniqueness.
            $uniqueid = abs(crc32($name)) ?: 1;
        }

        // Get URL based on node type - check instanceof FIRST.
        $url = null;

        if ($node instanceof \admin_settingpage) {
            // Settings page - use get_settings_page_url() which returns /admin/settings.php?section=name.
            try {
                $urlobj = $node->get_settings_page_url();
                $url = $urlobj->out(false);
            } catch (\Exception $e) {
                // Fallback: build URL manually.
                if (isset($node->name) && !empty($node->name)) {
                    $urlobj = new \moodle_url('/admin/settings.php', ['section' => $node->name]);
                    $url = $urlobj->out(false);
                }
            }
        } else if ($node instanceof \admin_externalpage) {
            // External page - use get_settings_page_url() which returns the external URL.
            try {
                $urlobj = $node->get_settings_page_url();
                $url = $urlobj->out(false);
            } catch (\Exception $e) {
                // Fallback: use url property directly.
                if (isset($node->url) && $node->url !== null) {
                    if (is_object($node->url) && method_exists($node->url, 'out')) {
                        $url = $node->url->out(false);
                    } else if (is_string($node->url)) {
                        $url = $node->url;
                    }
                }
            }
        } else if ($node instanceof \admin_category) {
            // Category - use get_settings_page_url() which returns /admin/category.php?category=name.
            try {
                $urlobj = $node->get_settings_page_url();
                $url = $urlobj->out(false);
            } catch (\Exception $e) {
                // Fallback: build URL manually.
                if (isset($node->name) && !empty($node->name)) {
                    $urlobj = new \moodle_url('/admin/category.php', ['category' => $node->name]);
                    $url = $urlobj->out(false);
                }
            }
        } else if (isset($node->url) && $node->url !== null) {
            // Node has a URL object directly (fallback for other node types).
            if (is_object($node->url) && method_exists($node->url, 'out')) {
                $url = $node->url->out(false);
            } else if (is_string($node->url)) {
                $url = $node->url;
            }
        }

        // Build keywords from name, visible name, and path segments.
        $keywords = [];
        if (!empty($name)) {
            $keywords[] = $name;
        }
        if (!empty($visiblename) && $visiblename !== $name) {
            $keywords[] = $visiblename;
        }
        foreach ($path as $pathsegment) {
            if ($pathsegment !== $name && $pathsegment !== $visiblename && !empty($pathsegment)) {
                $keywords[] = $pathsegment;
            }
        }

        if ($url === null || !\local_smartsearch\permissions::is_usable_result_url($url)) {
            return null;
        }

        return [
            'recordtype' => 'plugin',
            'recordid' => $uniqueid,
            'contextpath' => $pathstring,
            'title' => $visiblename,
            'subtitle' => $pathstring,
            'keywords' => array_unique($keywords),
            'url' => $url,
            'metadata' => [
                'path' => $path,
                'name' => $name,
                'visiblename' => $visiblename,
            ],
        ];
    }

    /**
     * Get plugin page URL.
     *
     * @param int $itemid Not used
     * @return \moodle_url|null
     */
    public function get_item_url(int $itemid): ?\moodle_url {
        return $this->get_indexed_item_url($itemid);
    }

    /**
     * Get plugin page actions.
     *
     * @param int $itemid Plugin page hash id
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
                'icon' => 'plug',
            ],
        ];
    }

    /**
     * Check if plugin page can be indexed/viewed.
     *
     * @param int $userid Current user ID
     * @param int $itemid Not used
     * @return bool
     */
    public function can_index(int $userid, int $itemid): bool {
        // Plugin area indexes the site admin tree; same gate as settings search.
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
        return 'plugin';
    }

    /**
     * Plugin pages use hash ids; orphan cleanup is handled separately.
     *
     * @param int $itemid Hash-based record id
     * @return bool
     */
    public function record_exists(int $itemid): bool {
        return true;
    }
}
