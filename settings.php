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
 * Smart Search settings page.
 *
 * @package    local_smartsearch
 * @copyright  2025 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
defined('MOODLE_INTERNAL') || die();

// Check if we're being included from admin settings system or called directly.
if (!empty($ADMIN)) {
    // Being included from admin settings - add external page links.
    if ($hassiteconfig) {
        $ADMIN->add('localplugins', new admin_externalpage(
            'localsmartsearch',
            get_string('settings', 'local_smartsearch'),
            $CFG->wwwroot . '/local/smartsearch/settings.php',
            'local/smartsearch:manage'
        ));

        // Add analytics report to Reports section in admin tree.
        $ADMIN->add('reports', new admin_externalpage(
            'localsmartsearchreport',
            get_string('report_analytics_title', 'local_smartsearch'),
            $CFG->wwwroot . '/local/smartsearch/report/',
            'local/smartsearch:manage'
        ));
    }
    return; // Stop here when included from admin settings.
}

// Standalone access - set up the page.
require_once($CFG->libdir . '/adminlib.php');

// Ensure we have access to required functions.
if (!function_exists('get_all_roles')) {
    require_once($CFG->libdir . '/accesslib.php');
}

$context = \context_system::instance();
require_login(null, false);
require_capability('local/smartsearch:manage', $context);

// Set up page - must be done in this order.
$url = new \moodle_url('/local/smartsearch/settings.php');
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');
$PAGE->set_pagetype('admin-local-smartsearch-settings');
$PAGE->set_title(get_string('settings', 'local_smartsearch'));
$PAGE->set_heading(get_string('settings', 'local_smartsearch'));
$PAGE->navbar->add(get_string('pluginname', 'local_smartsearch'), $url);

// Load CSS and JS before header is printed.
$PAGE->requires->css('/local/smartsearch/styles.css');

// Load required strings for JavaScript.
$strings = [
    'minchars',
    'searching',
    'noresults',
    'found_results',
    'error_unknown',
    'error_label',
    'starting_indexing',
    'indexing_in_progress_items',
    'indexing_already_running',
    'error_starting_indexing',
    'indexed_stats',
    'indexingcomplete',
    'indexing_warning',
    'indexing_warning_desc',
    'last_indexed',
    'search',
];
$PAGE->requires->strings_for_js($strings, 'local_smartsearch');

$PAGE->requires->js_call_amd('local_smartsearch/settings', 'init');

try {
    $form = new \local_smartsearch\form\settings();
} catch (\Exception $e) {
    // If form fails to load, show error.
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('settings', 'local_smartsearch'));
    echo $OUTPUT->notification(get_string('error_loading_form', 'local_smartsearch', $e->getMessage()), 'error');
    echo $OUTPUT->footer();
    die;
}

if ($form->is_cancelled()) {
    redirect(new \moodle_url('/admin/settings.php', ['section' => 'local_smartsearch']));
} else if ($data = $form->get_data()) {
    $rawretention = optional_param('local_smartsearch_analytics_retention', null, PARAM_INT);
    if ($rawretention !== null) {
        if ($rawretention === '') {
            $data->local_smartsearch_analytics_retention = 365;
        } else if (is_numeric($rawretention)) {
            $rawretention = (int) $rawretention;
            if ($rawretention < 0) {
                $data->local_smartsearch_analytics_retention = 365;
            }
        } else {
            $data->local_smartsearch_analytics_retention = 365;
        }
    }
    // Check if Smart Search is being enabled (before saving config).
    // Check if Smart Search was previously disabled (to detect enabling).
    $wasenabled = get_config('local_smartsearch', 'enable');
    $smartsearchenabled = isset($data->local_smartsearch_enable) &&
                          ($data->local_smartsearch_enable == 1 || $data->local_smartsearch_enable === '1');

    // Handle form submission - save all config values.
    foreach ($data as $key => $value) {
        if (strpos($key, 'local_smartsearch_') === 0) {
            $configkey = str_replace('local_smartsearch_', '', $key);
            // Skip role visibility settings (removed - using capability-based filtering instead).
            if (strpos($configkey, 'visibility_') === 0) {
                continue;
            }
            set_config($configkey, $value, 'local_smartsearch');
        }
    }

    // Handle Global Search disable/enable.
    if ($smartsearchenabled) {
        set_config('enableglobalsearch', 0); // Disable Global Search.

        // If Smart Search was just enabled (wasn't enabled before), trigger automatic indexing.
        if (!$wasenabled) {
            try {
                // Set indexing in progress flag.
                set_config('indexing_in_progress', time(), 'local_smartsearch');

                // Start indexing process.
                $stats = \local_smartsearch\indexer::index_all(0, function ($stats) {
                    // Update progress stats.
                    set_config('indexing_stats', json_encode($stats), 'local_smartsearch');
                });

                // Update final stats.
                set_config('indexing_stats', json_encode($stats), 'local_smartsearch');
                set_config('last_index_time', time(), 'local_smartsearch');
                unset_config('indexing_in_progress', 'local_smartsearch');

                // Store error details if any.
                if (!empty($stats['error_details'])) {
                    set_config('indexing_error_details', json_encode($stats['error_details']), 'local_smartsearch');
                } else {
                    unset_config('indexing_error_details', 'local_smartsearch');
                }

                $message = get_string('globalsearch_disabled', 'local_smartsearch') . '<br>';
                if ($stats['errors'] > 0) {
                    $message .= get_string('indexingcomplete', 'local_smartsearch') . ' ' .
                               get_string('indexed_stats', 'local_smartsearch', $stats);
                    $notificationtype = \core\output\notification::NOTIFY_WARNING;
                } else {
                    $message .= get_string('indexingcomplete', 'local_smartsearch') .
                               ' (' . get_string('indexed_stats', 'local_smartsearch', $stats) . ')';
                    $notificationtype = \core\output\notification::NOTIFY_SUCCESS;
                }

                redirect($url, $message, null, $notificationtype);
            } catch (\Exception $e) {
                unset_config('indexing_in_progress', 'local_smartsearch');
                // Don't fail the save - just log the error and continue.
                debugging('Auto-indexing failed when enabling Smart Search: ' . $e->getMessage(), DEBUG_NORMAL);
                redirect(
                    $url,
                    get_string('globalsearch_disabled', 'local_smartsearch') . ' ' .
                       get_string('error_indexing', 'local_smartsearch', $e->getMessage()),
                    null,
                    \core\output\notification::NOTIFY_WARNING
                );
            }
        } else {
            // Already enabled, just save changes.
            redirect($url, get_string('changessaved', 'moodle'), null, \core\output\notification::NOTIFY_SUCCESS);
        }
    } else {
        set_config('enableglobalsearch', 1); // Re-enable Global Search.
        redirect($url, get_string('changessaved', 'moodle'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

// Handle "Index Now" action.
if (optional_param('indexnow', false, PARAM_BOOL)) {
    require_sesskey();

    try {
        // Set indexing in progress flag.
        set_config('indexing_in_progress', time(), 'local_smartsearch');

        // Start indexing process.
        $stats = \local_smartsearch\indexer::index_all(0, function ($stats) {
            // Update progress stats.
            set_config('indexing_stats', json_encode($stats), 'local_smartsearch');
        });

        // Update final stats.
        set_config('indexing_stats', json_encode($stats), 'local_smartsearch');
        set_config('last_index_time', time(), 'local_smartsearch');
        unset_config('indexing_in_progress', 'local_smartsearch');

        // Store error details if any.
        if (!empty($stats['error_details'])) {
            set_config('indexing_error_details', json_encode($stats['error_details']), 'local_smartsearch');
        } else {
            unset_config('indexing_error_details', 'local_smartsearch');
        }

        $message = get_string('indexingcomplete', 'local_smartsearch');
        if ($stats['errors'] > 0) {
            $message .= ' ' . get_string('indexed_stats', 'local_smartsearch', $stats);
        }

        $notificationtype = ($stats['errors'] > 0)
            ? \core\output\notification::NOTIFY_WARNING
            : \core\output\notification::NOTIFY_SUCCESS;
        redirect($url, $message, null, $notificationtype);
    } catch (\Exception $e) {
        unset_config('indexing_in_progress', 'local_smartsearch');
        $errormsg = get_string('error_indexing', 'local_smartsearch', $e->getMessage());
        redirect($url, $errormsg, null, \core\output\notification::NOTIFY_ERROR);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('settings', 'local_smartsearch'));

// Display indexing errors if any.
$errordetailsjson = get_config('local_smartsearch', 'indexing_error_details');
if (!empty($errordetailsjson)) {
    $errordetails = json_decode($errordetailsjson, true);
    if (!empty($errordetails) && is_array($errordetails)) {
        $errors = [];
        foreach ($errordetails as $error) {
            $errors[] = [
                'type' => $error['type'],
                'item' => is_numeric($error['item']) ? '#' . $error['item'] : $error['item'],
                'message' => $error['error'],
            ];
        }
        echo $OUTPUT->render_from_template('local_smartsearch/indexing_errors', [
            'heading' => get_string('indexing_errors', 'local_smartsearch', count($errordetails)),
            'errors' => $errors,
        ]);

        // Clear error details after displaying.
        unset_config('indexing_error_details', 'local_smartsearch');
    }
}

// Display the form.
try {
    $form->display();
} catch (\Exception $e) {
    echo $OUTPUT->notification(get_string('error_displaying_form', 'local_smartsearch', $e->getMessage()), 'error');
    echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
}

echo $OUTPUT->footer();
