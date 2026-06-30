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
 * AJAX endpoint for Smart Search.
 *
 * @package    local_smartsearch
 * @copyright  2025 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_login();

// Check capability.
require_capability('local/smartsearch:search', \context_system::instance());

$query = required_param('q', PARAM_TEXT);
$limit = optional_param('limit', 50, PARAM_INT);

// Trim and validate query.
$query = trim($query);
if (strlen($query) < 3) {
    echo json_encode([]);
    die;
}

// Perform search.
$results = \local_smartsearch\query::search($query, $USER->id, $limit);

// Format results for frontend.
$formatted = [];

foreach ($results as $recordtype => $items) {
    // Skip debug info.
    if ($recordtype === '_debug') {
        continue;
    }

    $formatted[$recordtype] = [];
    foreach ($items as $item) {
        // Skip debug info in items.
        if (is_array($item) && isset($item['_debug'])) {
            continue;
        }

        // Convert moodle_url objects to strings.
        $url = is_object($item) ? $item->url : ($item['url'] ?? '');
        if (is_object($url) && method_exists($url, 'out')) {
            $url = $url->out(false);
        } else if (!is_string($url)) {
            $url = (string) $url;
        }

        $itemid = 0;
        $itemrecordid = 0;
        $itemrecordtype = '';
        $itemtitle = '';
        $itemsubtitle = '';
        $itemcontextpath = '';
        $itemactions = [];
        $itemrelevance = 0;

        if (is_object($item)) {
            $itemid = $item->id ?? 0;
            $itemrecordid = $item->recordid ?? 0;
            $itemrecordtype = $item->recordtype ?? '';
            $itemtitle = $item->title ?? '';
            $itemsubtitle = $item->subtitle ?? '';
            $itemcontextpath = $item->contextpath ?? '';
            $itemactions = $item->actions ?? [];
            $itemrelevance = $item->relevance_score ?? 0;
        } else if (is_array($item)) {
            $itemid = $item['id'] ?? 0;
            $itemrecordid = $item['recordid'] ?? 0;
            $itemrecordtype = $item['recordtype'] ?? '';
            $itemtitle = $item['title'] ?? '';
            $itemsubtitle = $item['subtitle'] ?? '';
            $itemcontextpath = $item['contextpath'] ?? '';
            $itemactions = $item['actions'] ?? [];
            $itemrelevance = $item['relevance_score'] ?? 0;
        }

        $formatted[$recordtype][] = [
            'id' => $itemid,
            'recordid' => $itemrecordid,
            'recordtype' => $itemrecordtype,
            'title' => $itemtitle,
            'subtitle' => $itemsubtitle,
            'contextpath' => $itemcontextpath,
            'url' => $url,
            'actions' => $itemactions,
            'relevance_score' => (int) $itemrelevance,
        ];
    }
}

// Use Moodle's JSON output.
echo json_encode($formatted);
