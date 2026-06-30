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
 * Smart Search Analytics Report.
 *
 * @package    local_smartsearch
 * @subpackage report
 * @copyright  2025 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/lib.php');

$context = \context_system::instance();
require_login();
require_capability('local/smartsearch:manage', $context);

// Check if analytics is enabled.
if (!get_config('local_smartsearch', 'enable_analytics')) {
    redirect(
        new \moodle_url('/local/smartsearch/settings.php'),
        get_string('analytics_not_enabled', 'local_smartsearch'),
        null,
        \core\output\notification::NOTIFY_WARNING
    );
}

// Prepare page.
$heading = get_string('report_analytics_title', 'local_smartsearch');
$pagetitle = $heading;
$url = new \moodle_url('/local/smartsearch/report/');
$settingsurl = new \moodle_url('/local/smartsearch/settings.php');

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->add_body_class('limitedwidth');
$PAGE->set_heading($heading);
$PAGE->set_title($pagetitle);
$PAGE->requires->css('/local/smartsearch/report/css/style.css');

// Add navigation breadcrumbs.
$PAGE->navbar->add(get_string('pluginname', 'local_smartsearch'), $settingsurl);
$PAGE->navbar->add($heading, $url);

// Get filter parameters.
$filterparams = [];
$filterparams['query'] = optional_param('query', '', PARAM_TEXT);
$startdate = optional_param_array('start_date', null, PARAM_INT);
$enddate = optional_param_array('end_date', null, PARAM_INT);
$filterparams['result_count_min'] = optional_param('result_count_min', '', PARAM_INT);
$filterparams['result_count_max'] = optional_param('result_count_max', '', PARAM_INT);
$filterparams['clicked'] = optional_param('clicked', '', PARAM_TEXT);

// Process date filters.
if (!empty($startdate) && !empty($startdate['enabled'])) {
    $filterparams['start_date'] = make_timestamp(
        $startdate['year'],
        $startdate['month'],
        $startdate['day'],
        0,
        0,
        0
    );
} else {
    $filterparams['start_date'] = '';
}

if (!empty($enddate) && !empty($enddate['enabled'])) {
    $filterparams['end_date'] = make_timestamp(
        $enddate['year'],
        $enddate['month'],
        $enddate['day'],
        23,
        59,
        59
    );
} else {
    $filterparams['end_date'] = '';
}

// Handle form submission.
$filterform = new \local_smartsearch\report\filter_form($url->out(false), null, 'get');
if ($filterform->is_cancelled()) {
    // Reset filters.
    redirect($url);
}

// Get filter data from form.
$filterdata = $filterform->get_data();
if ($filterdata) {
    // Update filter params from form data.
    if (!empty($filterdata->query)) {
        $filterparams['query'] = $filterdata->query;
    }
    if (!empty($filterdata->start_date['enabled'])) {
        $filterparams['start_date'] = make_timestamp(
            $filterdata->start_date['year'],
            $filterdata->start_date['month'],
            $filterdata->start_date['day'],
            0,
            0,
            0
        );
    }
    if (!empty($filterdata->end_date['enabled'])) {
        $filterparams['end_date'] = make_timestamp(
            $filterdata->end_date['year'],
            $filterdata->end_date['month'],
            $filterdata->end_date['day'],
            23,
            59,
            59
        );
    }
    if (isset($filterdata->result_count_min)) {
        $filterparams['result_count_min'] = $filterdata->result_count_min;
    }
    if (isset($filterdata->result_count_max)) {
        $filterparams['result_count_max'] = $filterdata->result_count_max;
    }
    if (isset($filterdata->clicked)) {
        $filterparams['clicked'] = $filterdata->clicked;
    }
}

// Set default values for form.
$formdata = [
    'query' => $filterparams['query'],
    'result_count_min' => $filterparams['result_count_min'],
    'result_count_max' => $filterparams['result_count_max'],
    'clicked' => $filterparams['clicked'],
];

// Handle date selectors - they need array format.
if (!empty($filterparams['start_date'])) {
    $formdata['start_date'] = [
        'enabled' => 1,
        'day' => (int)date('j', $filterparams['start_date']),
        'month' => (int)date('n', $filterparams['start_date']),
        'year' => (int)date('Y', $filterparams['start_date']),
    ];
}

if (!empty($filterparams['end_date'])) {
    $formdata['end_date'] = [
        'enabled' => 1,
        'day' => (int)date('j', $filterparams['end_date']),
        'month' => (int)date('n', $filterparams['end_date']),
        'year' => (int)date('Y', $filterparams['end_date']),
    ];
}

$filterform->set_data($formdata);

// Create table.
$uniqueid = 'local_smartsearch_analytics';
$table = new \local_smartsearch\report\analytics_table($uniqueid, $filterparams);
$table->define_baseurl($url);

// Handle download - MUST be checked before any output.
$download = optional_param('download', '', PARAM_ALPHA);
if (!empty($download)) {
    // Set downloading mode before any output.
    $table->is_downloading($download, $table->get_download_filename());
    // Output the table data for download and exit.
    \core\session\manager::write_close();
    $table->out(0, false);
    exit;
}

// Only output HTML if not downloading.
echo $OUTPUT->header();

// Display description.
echo html_writer::div(get_string('report_analytics_desc', 'local_smartsearch'), 'mb-3');

// Get analytics data for chart.
$topqueries = local_smartsearch_get_top_queries($filterparams, 10);

// Prepare chart data for template.
$chartsdata = [];

// Most searched words - Pie Chart.
if (!empty($topqueries)) {
    $labels = [];
    foreach (array_keys($topqueries) as $query) {
        if (\core_text::strlen($query) > 24) {
            $labels[] = \core_text::substr($query, 0, 21) . '...';
        } else {
            $labels[] = $query;
        }
    }
    $values = array_values($topqueries);

    $series = new \core\chart_series(get_string('report_chart_searches', 'local_smartsearch'), $values);
    $chart = new \core\chart_pie();
    $chart->set_title(get_string('report_chart_top_queries_title', 'local_smartsearch'));
    $chart->add_series($series);
    $chart->set_labels($labels);
    $chart->set_legend_options([
        'position' => 'bottom',
        'align' => 'start',
        'labels' => [
            'boxWidth' => 12,
            'padding' => 10,
            'usePointStyle' => true,
        ],
    ]);
    $chart->set_responsive_options([
        'responsive' => true,
        'maintainAspectRatio' => false,
    ]);

    $chartsdata[] = [
        'title' => get_string('report_chart_top_queries', 'local_smartsearch'),
        'charthtml' => $OUTPUT->render($chart),
    ];
}

// Display analytics chart above filter form using template.
if (!empty($chartsdata)) {
    $templatecontext = [
        'charts' => $chartsdata,
    ];
    echo $OUTPUT->render_from_template('local_smartsearch/analytics_charts', $templatecontext);
}

// Display filter form.
$filterform->display();

// Display table.
$table->out(30, true);

echo $OUTPUT->footer();
