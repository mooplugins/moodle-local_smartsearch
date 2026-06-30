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
 * Smart Search results page.
 *
 * @package    local_smartsearch
 * @copyright  2025 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$context = \context_system::instance();
require_login();
require_capability('local/smartsearch:search', $context);

$query = optional_param('q', '', PARAM_TEXT);
$query = trim($query);

$url = new \moodle_url('/local/smartsearch/results.php', ['q' => $query]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('search_results_title', 'local_smartsearch'));
$PAGE->set_heading(get_string('search_results_title', 'local_smartsearch'));
$PAGE->add_body_class('smartsearch-results-page');

$PAGE->requires->css('/local/smartsearch/styles.css');

// Ensure JS strings used by the renderer are available.
$strings = [
    'results_users',
    'results_courses',
    'results_activities',
    'results_settings',
    'results_plugins',
    'results_categories',
    'search',
    'searchtitle',
    'searchplaceholder',
    'resultslabel',
    'loading',
    'noresults',
    'error_search_generic',
    'error_label',
    'view_all_results',
    'pagination_previous',
    'pagination_next',
    'showing_results_count',
    'showing_result_count',
    'error_no_response',
    'error_search_occurred',
    'error_parse_results',
    'error_results_container',
    'error_unknown_occurred',
    'enrolledincourses',
];
$PAGE->requires->strings_for_js($strings, 'local_smartsearch');
$PAGE->requires->js_call_amd('local_smartsearch/search', 'init');

echo $OUTPUT->header();

$filters = [
    ['key' => 'course', 'label' => get_string('results_courses', 'local_smartsearch')],
    ['key' => 'plugin', 'label' => get_string('results_plugins', 'local_smartsearch')],
    ['key' => 'setting', 'label' => get_string('results_settings', 'local_smartsearch')],
    ['key' => 'activity', 'label' => get_string('results_activities', 'local_smartsearch')],
    ['key' => 'user', 'label' => get_string('results_users', 'local_smartsearch')],
    ['key' => 'category', 'label' => get_string('results_categories', 'local_smartsearch')],
];

$templatecontext = [
    'query' => $query,
    'search' => get_string('search', 'local_smartsearch'),
    'searchtitle' => get_string('searchtitle', 'local_smartsearch'),
    'searchplaceholder' => get_string('searchplaceholder', 'local_smartsearch'),
    'resultslabel' => get_string('resultslabel', 'local_smartsearch'),
    'filterslabel' => get_string('results_filters_label', 'local_smartsearch'),
    'paginationlabel' => get_string('pagination_label', 'local_smartsearch'),
    'filters' => $filters,
];

echo $OUTPUT->render_from_template('local_smartsearch/searchpage', $templatecontext);

echo $OUTPUT->footer();
