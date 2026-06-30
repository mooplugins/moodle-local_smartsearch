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
 * Analytics table for Smart Search report.
 *
 * @package    local_smartsearch
 * @copyright  2025 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_smartsearch\report;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/tablelib.php');

/**
 * Table class for displaying Smart Search analytics data.
 *
 * @package    local_smartsearch
 * @copyright  2025 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class analytics_table extends \table_sql {
    /** @var array Filter parameters */
    private $filterparams;

    /**
     * Constructor.
     *
     * @param string $uniqueid Unique identifier for the table
     * @param array $filterparams Filter parameters
     */
    public function __construct($uniqueid, $filterparams = []) {
        parent::__construct($uniqueid);
        $this->filterparams = $filterparams;

        $this->define_columns([
            'id',
            'query',
            'result_count',
            'timestamp',
        ]);

        $this->define_headers([
            get_string('report_column_id', 'local_smartsearch'),
            get_string('report_column_query', 'local_smartsearch'),
            get_string('report_column_result_count', 'local_smartsearch'),
            get_string('report_column_timestamp', 'local_smartsearch'),
        ]);

        $this->collapsible(false);
        $this->sortable(true);
        $this->pageable(true);
        $this->is_downloadable(true);
        $this->show_download_buttons_at([TABLE_P_TOP, TABLE_P_BOTTOM]);

        $this->sort_default_column = 'timestamp';
        $this->sort_default_order = SORT_DESC;

        $this->setup_sql();
    }

    /**
     * Set up SQL query.
     */
    private function setup_sql() {
        global $DB;

        $fields = 'id, query, result_count, clicked_result_id, timestamp';
        $from = '{local_smartsearch_log}';
        $where = '1=1';
        $params = [];

        if (!empty($this->filterparams['query'])) {
            $where .= ' AND ' . $DB->sql_like('query', ':query', false, false);
            $params['query'] = '%' . $DB->sql_like_escape($this->filterparams['query']) . '%';
        }

        if (!empty($this->filterparams['start_date'])) {
            $where .= ' AND timestamp >= :start_date';
            $params['start_date'] = (int)$this->filterparams['start_date'];
        }

        if (!empty($this->filterparams['end_date'])) {
            $where .= ' AND timestamp <= :end_date';
            $params['end_date'] = (int)$this->filterparams['end_date'];
        }

        if (isset($this->filterparams['result_count_min']) && $this->filterparams['result_count_min'] !== '') {
            $where .= ' AND result_count >= :result_min';
            $params['result_min'] = (int)$this->filterparams['result_count_min'];
        }

        if (isset($this->filterparams['result_count_max']) && $this->filterparams['result_count_max'] !== '') {
            $where .= ' AND result_count <= :result_max';
            $params['result_max'] = (int)$this->filterparams['result_count_max'];
        }

        if (isset($this->filterparams['clicked']) && $this->filterparams['clicked'] !== '') {
            if ($this->filterparams['clicked'] == '1') {
                $where .= ' AND clicked_result_id IS NOT NULL';
            } else {
                $where .= ' AND clicked_result_id IS NULL';
            }
        }

        parent::set_sql($fields, $from, $where, $params);
    }

    /**
     * Format the query column.
     *
     * @param \stdClass $row Row data
     * @return string Formatted query
     */
    public function col_query($row) {
        if (empty($this->download)) {
            return format_string($row->query);
        }

        return $row->query;
    }

    /**
     * Format the result_count column.
     *
     * @param \stdClass $row Row data
     * @return string Formatted count
     */
    public function col_result_count($row) {
        return (int)$row->result_count;
    }

    /**
     * Format the timestamp column.
     *
     * @param \stdClass $row Row data
     * @return string Formatted timestamp
     */
    public function col_timestamp($row) {
        if (empty($this->download)) {
            $dateformat = get_string('strftimedatetime', 'core_langconfig');
        } else {
            $dateformat = get_string('strftimedatetimeshort', 'core_langconfig');
        }
        return userdate($row->timestamp, $dateformat);
    }

    /**
     * Get the download filename.
     *
     * @return string Filename
     */
    public function get_download_filename() {
        return 'smartsearch_analytics_' . userdate(time(), '%Y%m%d');
    }
}
