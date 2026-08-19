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
 * Smart Search plugin library functions.
 *
 * @package    local_smartsearch
 * @copyright  2026 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Add navigation nodes for Smart Search.
 *
 * @param \global_navigation $navigation The global navigation object
 * @return void
 */
function local_smartsearch_extend_navigation(\global_navigation $navigation) {
    global $PAGE;

    // Only add if Smart Search is enabled.
    if (!get_config('local_smartsearch', 'enable')) {
        return;
    }

    // Match theme learner core_renderer::search_box visibility (login + moodle/search:query).
    if (!isloggedin() || isguestuser()) {
        return;
    }
    $systemcontext = \context_system::instance();
    if (!has_capability('moodle/search:query', $systemcontext)) {
        return;
    }

    // Prevent multiple initializations by checking if already initialized.
    static $initialized = false;
    if ($initialized) {
        return;
    }
    $initialized = true;

    // Load required strings for JavaScript.
    $strings = [
        'search',
        'searchtitle',
        'searchplaceholder',
        'clearsearch',
        'shortcut_close',
        'shortcut_esc',
        'shortcut_navigate',
        'shortcut_select',
        'loading',
        'noresults',
        'error_search_generic',
        'error_label',
        'error_no_response',
        'error_search_occurred',
        'error_parse_results',
        'error_results_container',
        'error_unknown_occurred',
        'resultslabel',
        'results_users',
        'results_courses',
        'results_activities',
        'results_settings',
        'results_plugins',
        'results_categories',
        'enrolledincourses',
        'view_all_results',
        'feedback_link',
        'pagination_previous',
        'pagination_next',
        'showing_results_count',
        'showing_result_count',
    ];
    $PAGE->requires->css(new \moodle_url('/local/smartsearch/styles.css', ['rev' => '2026081904']));
    $PAGE->requires->strings_for_js($strings, 'local_smartsearch');

    // Load JavaScript module (CSS should be loaded in page-specific hooks, not here).
    $PAGE->requires->js_call_amd('local_smartsearch/search', 'init', [local_smartsearch_get_ui_config()]);
}

/**
 * Whether the current user may submit Smart Search product feedback.
 *
 * Feedback emails include the submitter's name and email, so this is restricted
 * to site administrators and users with the manager role at system level.
 *
 * @return bool
 */
function local_smartsearch_can_submit_feedback(): bool {
    $context = \context_system::instance();
    return has_capability('local/smartsearch:feedback', $context);
}

/**
 * UI config passed to the search AMD module.
 *
 * @return array{accentMode: string, accentColor: string, canFeedback: bool}
 */
function local_smartsearch_get_ui_config(): array {
    $mode = get_config('local_smartsearch', 'accent_mode');
    if (!in_array($mode, ['default', 'theme', 'custom'], true)) {
        $mode = 'default';
    }
    $color = (string) get_config('local_smartsearch', 'accent_color');
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
        $color = '#1C6CF7';
    }

    return [
        'accentMode' => $mode,
        'accentColor' => $color,
        'canFeedback' => local_smartsearch_can_submit_feedback(),
    ];
}

/**
 * Serve the search AJAX requests.
 *
 * @param \stdClass $course Course object
 * @param \stdClass $cm Course module object
 * @param \context $context Context object
 * @param string $filearea File area
 * @param array $args Arguments
 * @param bool $forcedownload Force download
 * @param array $options Options
 * @return bool False - no file serving needed
 */
function local_smartsearch_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    // No file serving needed for this plugin.
    return false;
}
