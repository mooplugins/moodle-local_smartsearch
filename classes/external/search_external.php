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
 * External API for Smart Search.
 *
 * @package    local_smartsearch
 * @copyright  2025 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_smartsearch\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * External search API.
 */
class search_external extends \external_api {
    /**
     * Search parameters.
     *
     * @return \external_function_parameters
     */
    public static function search_parameters() {
        return new \external_function_parameters([
            'query' => new \external_value(PARAM_TEXT, 'Search query'),
            'limit' => new \external_value(PARAM_INT, 'Maximum results per category', VALUE_DEFAULT, 50),
        ]);
    }

    /**
     * Search return values.
     *
     * @return \external_multiple_structure
     */
    public static function search_returns() {
        return new \external_single_structure([
            // PARAM_RAW is required for JSON payloads: PARAM_TEXT strip_tags breaks encoded HTML in results.
            'results' => new \external_value(PARAM_RAW, 'JSON encoded results'),
        ]);
    }

    /**
     * Perform search.
     *
     * @param string $query Search query
     * @param int $limit Maximum results per category
     * @return array
     */
    public static function search(string $query, int $limit = 50) {
        global $USER;

        $params = self::validate_parameters(self::search_parameters(), [
            'query' => $query,
            'limit' => $limit,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/smartsearch:search', $context);

        // Perform search.
        $results = \local_smartsearch\query::search($params['query'], $USER->id, $params['limit']);

        // Format results for frontend.
        $formatted = [];
        foreach ($results as $recordtype => $items) {
            // Skip debug info if it's in items.
            if ($recordtype === '_debug') {
                continue;
            }

            // Skip if items is not an array or is empty.
            if (!is_array($items) || empty($items)) {
                continue;
            }

            $formatted[$recordtype] = [];
            foreach ($items as $item) {
                // Skip debug info in items.
                if (is_array($item) && isset($item['_debug'])) {
                    continue;
                }

                // Handle both array and object formats.
                $itemid = null;
                $itemtitle = null;
                if (is_array($item)) {
                    $itemid = $item['id'] ?? null;
                    $itemtitle = $item['title'] ?? null;
                } else if (is_object($item)) {
                    $itemid = $item->id ?? null;
                    $itemtitle = $item->title ?? null;
                }

                // Skip if item is null or doesn't have required properties.
                if (empty($item) || !$itemid || !$itemtitle) {
                    continue;
                }

                // Handle both array and object formats for all properties.
                $itemrecordid = null;
                $itemrecordtype = $recordtype;
                $itemsubtitle = '';
                $itemcontextpath = '';
                $itemurl = '';
                $itemactions = [];
                $itemrelevance = 0;

                if (is_array($item)) {
                    $itemrecordid = $item['recordid'] ?? null;
                    $itemrecordtype = $item['recordtype'] ?? $recordtype;
                    $itemsubtitle = $item['subtitle'] ?? '';
                    $itemcontextpath = $item['contextpath'] ?? '';
                    $itemurl = $item['url'] ?? '';
                    $itemactions = $item['actions'] ?? [];
                    $itemrelevance = $item['relevance_score'] ?? 0;
                } else if (is_object($item)) {
                    $itemrecordid = $item->recordid ?? null;
                    $itemrecordtype = $item->recordtype ?? $recordtype;
                    $itemsubtitle = $item->subtitle ?? '';
                    $itemcontextpath = $item->contextpath ?? '';
                    $itemurl = $item->url ?? '';
                    $itemactions = $item->actions ?? [];
                    $itemrelevance = $item->relevance_score ?? 0;
                }

                // Serialize URL if it's a moodle_url object.
                $url = $itemurl;
                if ($url instanceof \moodle_url) {
                    $url = $url->out(false);
                }
                if (empty($url)) {
                    $url = '#';
                }

                // Serialize action URLs.
                $actions = [];
                if (!empty($itemactions) && is_array($itemactions)) {
                    foreach ($itemactions as $action) {
                        if (!is_array($action)) {
                            continue;
                        }
                        $actionurl = $action['url'] ?? '';
                        if ($actionurl instanceof \moodle_url) {
                            $actionurl = $actionurl->out(false);
                        }
                        $actions[] = [
                            'label' => $action['label'] ?? '',
                            'url' => $actionurl,
                            'icon' => $action['icon'] ?? '',
                        ];
                    }
                }

                $formatted[$recordtype][] = [
                    'id' => $itemid,
                    'recordid' => $itemrecordid,
                    'recordtype' => $itemrecordtype,
                    'title' => self::sanitize_result_text($itemtitle),
                    'subtitle' => self::sanitize_result_text($itemsubtitle),
                    'contextpath' => self::sanitize_result_text($itemcontextpath),
                    'url' => $url,
                    'actions' => $actions,
                    'relevance_score' => (int) $itemrelevance,
                ];
            }
        }

        return [
            'results' => json_encode($formatted, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
        ];
    }

    /**
     * Sanitize text fields for JSON export and display.
     *
     * @param mixed $value Raw field value
     * @return string Plain text safe for JSON encoding
     */
    private static function sanitize_result_text($value): string {
        if ($value === null || $value === '') {
            return '';
        }
        if (!is_scalar($value)) {
            return '';
        }
        return trim(strip_tags((string) $value));
    }
}
