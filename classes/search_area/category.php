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
 * Course category search area.
 *
 * @package    local_smartsearch
 * @copyright  2025 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_smartsearch\search_area;


/**
 * Course category search area implementation.
 */
class category extends base {
    /**
     * Get the display name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('category_categories', 'local_smartsearch');
    }

    /**
     * Get the recordset of categories to index.
     *
     * @param int $modifiedfrom Timestamp
     * @return \moodle_recordset|null
     */
    public function get_recordset(int $modifiedfrom = 0): ?\moodle_recordset {
        global $DB;

        $params = [];
        $where = '1=1';

        if ($modifiedfrom > 0) {
            // Course_categories has timemodified, but no timecreated.
            $where .= ' AND timemodified > ?';
            $params[] = $modifiedfrom;
        }

        return $DB->get_recordset_select('course_categories', $where, $params, 'id ASC', 'id, name, description, parent, visible');
    }

    /**
     * Index a category.
     *
     * @param int $itemid Category ID
     * @param \stdClass|null $sourcerecord Optional source row from get_recordset()
     * @return array|null
     */
    public function index_item(int $itemid, ?\stdClass $sourcerecord = null): ?array {
        global $DB;

        $category = $sourcerecord ?? $DB->get_record('course_categories', ['id' => $itemid]);
        if (!$category) {
            return null;
        }

        $categorypath = \local_smartsearch\category_path::get_path($itemid);

        $subtitle = '';
        if ($categorypath && $categorypath !== $category->name) {
            $subtitle = $categorypath;
        }
        if (!empty($category->description)) {
            if ($subtitle) {
                $subtitle .= ' | ';
            }
            $subtitle .= strip_tags($category->description);
        }

        $categoryurl = new \moodle_url('/course/index.php', ['categoryid' => $itemid]);

        return [
            'recordtype' => 'category',
            'recordid' => $itemid,
            'contextpath' => $categorypath,
            'title' => $category->name,
            'subtitle' => $subtitle,
            'keywords' => [$category->name, 'category', 'course category'],
            'url' => $categoryurl->out(false),
            'metadata' => [
                'parent' => $category->parent,
                'visible' => (bool) $category->visible,
            ],
        ];
    }

    /**
     * Get category URL.
     *
     * @param int $itemid Category ID
     * @return \moodle_url|null
     */
    public function get_item_url(int $itemid): ?\moodle_url {
        return new \moodle_url('/course/index.php', ['categoryid' => $itemid]);
    }

    /**
     * Get category actions.
     *
     * @param int $itemid Category ID
     * @param int $userid Current user ID
     * @return array
     */
    public function get_item_actions(int $itemid, int $userid): array {
        $actions = [];

        // View category.
        $actions[] = [
            'label' => get_string('action_view', 'local_smartsearch'),
            'url' => new \moodle_url('/course/index.php', ['categoryid' => $itemid]),
            'icon' => 'folder-open',
        ];

        // Edit category (if permitted).
        if (has_capability('moodle/category:manage', \context_coursecat::instance($itemid), $userid)) {
            $actions[] = [
                'label' => get_string('action_edit', 'local_smartsearch'),
                'url' => new \moodle_url('/course/editcategory.php', ['id' => $itemid]),
                'icon' => 'edit',
            ];
        }

        return $actions;
    }

    /**
     * Check if category can be indexed/viewed.
     *
     * @param int $userid Current user ID
     * @param int $itemid Category ID
     * @return bool
     */
    public function can_index(int $userid, int $itemid): bool {
        global $DB;

        // Category results are restricted to site administrators only.
        if (!is_siteadmin($userid)) {
            return false;
        }

        try {
            // Check if category exists.
            $category = $DB->get_record('course_categories', ['id' => $itemid]);
            if (!$category) {
                return false;
            }

            $context = \context_coursecat::instance($itemid);
            // For hidden categories, require hidden-category capability.
            if (!$category->visible && !has_capability('moodle/category:viewhiddencategories', $context, $userid)) {
                return false;
            }

            // Show category only when user has qualifying enrollment in exact category.
            return \local_smartsearch\permissions::can_access_search_result_url(
                $userid,
                '/catalog/index.php?category=' . $itemid
            );
        } catch (\Exception $e) {
            // If there's an error (e.g., context doesn't exist), deny access for security.
            return false;
        }
    }

    /**
     * Get indexing frequency.
     *
     * @return string
     */
    public function get_indexing_frequency(): string {
        return 'realtime';
    }

    /**
     * Get record type.
     *
     * @return string
     */
    public function get_record_type(): string {
        return 'category';
    }

    /**
     * Check whether the source category still exists.
     *
     * @param int $itemid Category id
     * @return bool
     */
    public function record_exists(int $itemid): bool {
        global $DB;
        return $DB->record_exists('course_categories', ['id' => $itemid]);
    }
}
