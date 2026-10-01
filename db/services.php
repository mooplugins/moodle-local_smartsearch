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
 * Web service definitions for Smart Search.
 *
 * @package    local_smartsearch
 * @copyright  2026 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_smartsearch_search' => [
        'classname' => 'local_smartsearch\external\search_external',
        'methodname' => 'search',
        'classpath' => '',
        'description' => 'Perform a search query',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
        'capabilities' => 'local/smartsearch:search',
    ],
    'local_smartsearch_start_indexing' => [
        'classname' => 'local_smartsearch\external\index_external',
        'methodname' => 'start_indexing',
        'classpath' => '',
        'description' => 'Begin a chunked Smart Search reindex',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
        'capabilities' => 'local/smartsearch:manage',
    ],
    'local_smartsearch_process_indexing_chunk' => [
        'classname' => 'local_smartsearch\external\index_external',
        'methodname' => 'process_indexing_chunk',
        'classpath' => '',
        'description' => 'Process the next Smart Search indexing chunk',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
        'capabilities' => 'local/smartsearch:manage',
    ],
    'local_smartsearch_get_indexing_progress' => [
        'classname' => 'local_smartsearch\external\index_external',
        'methodname' => 'get_indexing_progress',
        'classpath' => '',
        'description' => 'Get Smart Search indexing progress',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
        'capabilities' => 'local/smartsearch:manage',
    ],
    'local_smartsearch_stop_indexing' => [
        'classname' => 'local_smartsearch\external\index_external',
        'methodname' => 'stop_indexing',
        'classpath' => '',
        'description' => 'Stop a running Smart Search reindex',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
        'capabilities' => 'local/smartsearch:manage',
    ],
    'local_smartsearch_submit_feedback' => [
        'classname' => 'local_smartsearch\external\feedback_external',
        'methodname' => 'submit_feedback',
        'classpath' => '',
        'description' => 'Submit Smart Search product feedback',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
        'capabilities' => 'local/smartsearch:feedback',
    ],
];
