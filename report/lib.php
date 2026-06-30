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
 * Smart Search Analytics Report library functions.
 *
 * @package    local_smartsearch
 * @subpackage report
 * @copyright  2025 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Get most searched words/queries.
 *
 * @param array $filters Filter parameters
 * @param int $limit Number of top queries to return
 * @return array Array of ['query' => count] pairs
 */
function local_smartsearch_get_top_queries(array $filters = [], int $limit = 10): array {
    global $DB;

    $params = [];
    $where = ['1=1'];

    // Apply date filters.
    if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
        $start = is_numeric($filters['start_date']) ? $filters['start_date'] : strtotime($filters['start_date'] . ' 00:00:00');
        $end = is_numeric($filters['end_date']) ? $filters['end_date'] : strtotime($filters['end_date'] . ' 23:59:59');
        $where[] = 'timestamp >= :start_date AND timestamp <= :end_date';
        $params['start_date'] = $start;
        $params['end_date'] = $end;
    }

    if (!empty($filters['query'])) {
        $where[] = $DB->sql_like('query', ':query', false, true);
        $params['query'] = '%' . $DB->sql_like_escape($filters['query']) . '%';
    }

    $wheresql = implode(' AND ', $where);

    // LIMIT doesn't accept named parameters, so we use the integer directly.
    // The limit is safe as it comes from a function parameter.
    $limit = (int)$limit;
    if ($limit <= 0) {
        $limit = 10;
    }

    $sql = "SELECT query, COUNT(*) as count
            FROM {local_smartsearch_log}
            WHERE {$wheresql}
            GROUP BY query
            ORDER BY count DESC";

    $records = $DB->get_records_sql($sql, $params, 0, $limit);

    $result = [];
    foreach ($records as $record) {
        $result[$record->query] = (int)$record->count;
    }

    return $result;
}
