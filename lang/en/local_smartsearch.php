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
 * Language strings for Smart Search plugin.
 *
 * @package    local_smartsearch
 * @copyright  2026 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['accent_custom'] = 'Custom accent color';
$string['accent_custom_desc'] = 'Hex color used when custom accent is selected. Example: #1C6CF7';
$string['accent_custom_invalid'] = 'Enter a 6-digit hex color such as #1C6CF7.';
$string['accent_mode'] = 'Accent color';
$string['accent_mode_custom'] = 'Custom accent color';
$string['accent_mode_default'] = 'Smart Search default';
$string['accent_mode_desc'] = 'Use the Smart Search blue by default. Theme primary is optional because some themes use a primary color that works poorly for search focus and selected results. Neutral surfaces stay controlled by Smart Search.';
$string['accent_mode_theme'] = 'Use Moodle theme primary color';
$string['action_edit'] = 'Edit';
$string['action_loginas'] = 'Login As';
$string['action_sendmessage'] = 'Send Message';
$string['action_view'] = 'View';
$string['action_viewprofile'] = 'View Profile';
$string['analytics'] = 'Analytics';
$string['analytics_not_enabled'] = 'Analytics is not enabled. Please enable analytics in Smart Search settings to view this report.';
$string['analytics_retention'] = 'Analytics Retention Period';
$string['analytics_retention_desc'] = 'How long to keep analytics data (in days). Use 0 to keep data indefinitely. Default: 365 days.';
$string['appearance'] = 'Appearance';
$string['category_activities'] = 'Activities';
$string['category_categories'] = 'Categories';
$string['category_courses'] = 'Courses';
$string['category_plugins'] = 'Plugin Pages';
$string['category_settings'] = 'Settings';
$string['category_users'] = 'Users';
$string['clearsearch'] = 'Clear search';
$string['default_value_label'] = 'Default: {$a}';
$string['enable'] = 'Enable Smart Search';
$string['enable_activities'] = 'Enable Activity Search';
$string['enable_activities_desc'] = 'Allow searching for activities and resources.';
$string['enable_analytics'] = 'Enable Analytics';
$string['enable_analytics_desc'] = 'Enable anonymous search analytics.';
$string['enable_categories'] = 'Enable Category Search';
$string['enable_categories_desc'] = 'Allow searching for course categories.';
$string['enable_courses'] = 'Enable Course Search';
$string['enable_courses_desc'] = 'Allow searching for courses.';
$string['enable_desc'] = 'Enable Smart Search to replace Global Search. When enabled, Global Search will be automatically disabled.';
$string['enable_plugins'] = 'Enable Plugin Pages Search';
$string['enable_plugins_desc'] = 'Allow searching for plugin and admin pages.';
$string['enable_settings'] = 'Enable Settings Search';
$string['enable_settings_desc'] = 'Allow searching for site settings.';
$string['enable_users'] = 'Enable User Search';
$string['enable_users_desc'] = 'Allow searching for users.';
$string['enrolledincourses'] = 'Enrolled in courses';
$string['enrolledusers'] = 'Enrolled users';
$string['error_displaying_form'] = 'Error displaying form: {$a}';
$string['error_indexing'] = 'Error during indexing: {$a}';
$string['error_invalid_action'] = 'Invalid action.';
$string['error_label'] = 'Error:';
$string['error_loading_form'] = 'Error loading form: {$a}';
$string['error_missing_action'] = 'Missing action parameter.';
$string['error_no_response'] = 'No response from server';
$string['error_parse_results'] = 'Error parsing search results: {$a}';
$string['error_permission'] = 'You do not have permission to perform this action.';
$string['error_results_container'] = 'Results container not found';
$string['error_search_generic'] = 'Couldn\'t complete this search. Refresh the page and try again.';
$string['error_search_occurred'] = 'Couldn\'t complete this search. Refresh the page and try again.';
$string['error_server'] = 'Server error.';
$string['error_starting_indexing'] = 'Error starting indexing';
$string['error_unknown'] = 'Unknown error';
$string['error_unknown_occurred'] = 'Unknown error occurred';
$string['feedback_cancel'] = 'Cancel';
$string['feedback_description'] = 'Your feedback helps us improve search for Moodle administrators, Teachers and Students.';
$string['feedback_done'] = 'Close';
$string['feedback_email_subject'] = 'Smart Search feedback: {$a}';
$string['feedback_include_tech'] = 'Include technical details';
$string['feedback_include_tech_help'] = 'Moodle version, Smart Search version, and browser information.';
$string['feedback_link'] = 'Help us improve Smart Search';
$string['feedback_message_label'] = 'Tell us more';
$string['feedback_message_placeholder'] = 'What were you trying to find, and what would have made Smart Search work better for you?';
$string['feedback_message_required'] = 'Please enter your feedback.';
$string['feedback_privacy_note'] = 'When you send feedback, your name and email address will be shared with the MooPlugins team so we can understand your feedback and contact you if needed.';
$string['feedback_required'] = '*';
$string['feedback_send_failed'] = 'Could not send feedback. Please try again later.';
$string['feedback_submit'] = 'Send feedback';
$string['feedback_success_description'] = 'We read every submission and use feedback to guide future improvements.';
$string['feedback_success_title'] = 'Thanks for helping us improve Smart Search.';
$string['feedback_title'] = 'Help us improve Smart Search';
$string['feedback_type_improvement'] = 'Suggest an improvement';
$string['feedback_type_irrelevant'] = 'Search result isn\'t relevant';
$string['feedback_type_label'] = 'What would you like to share?';
$string['feedback_type_missing'] = 'Missing search result';
$string['feedback_type_notworking'] = 'Something isn\'t working';
$string['feedback_type_other'] = 'Other feedback';
$string['found_results'] = 'Found results in {$a} categories:';
$string['globalsearch_disabled'] = 'Global Search has been disabled because Smart Search is enabled.';
$string['in'] = 'In';
$string['indexed_stats'] = 'Indexed: {$a->indexed} / {$a->total} (Skipped: {$a->skipped}, Errors: {$a->errors})';
$string['indexing'] = 'Indexing';
$string['indexing_already_running'] = 'Indexing already in progress...';
$string['indexing_cancelled'] = 'Indexing was cancelled.';
$string['indexing_errors'] = 'Indexing Errors ({$a})';
$string['indexing_in_progress_items'] = 'Indexing... ({$a} items processed)';
$string['indexing_warning'] = 'Indexing in progress. Please do not refresh or close this page.';
$string['indexing_warning_desc'] = 'The indexing process is running in the background. Refreshing or closing this page may interrupt the process.';
$string['indexingcomplete'] = 'Indexing complete!';
$string['indexnow'] = 'Index now';
$string['indexnow_desc'] = 'Manually trigger a full re-index of all searchable content.';
$string['last_indexed'] = 'Last indexed: {$a}';
$string['last_indexed_never'] = 'Never';
$string['loading'] = 'Loading...';
$string['minchars'] = 'Please enter at least 3 characters';
$string['modulename'] = 'Module name';
$string['noresults'] = 'No results found';
$string['pagination_label'] = 'Search result pages';
$string['pagination_next'] = 'Next';
$string['pagination_previous'] = 'Previous';
$string['participants'] = 'Participants';
$string['pluginname'] = 'Smart Search';
$string['privacy:metadata:local_smartsearch_index'] = 'Stores searchable content copies used by Smart Search.';
$string['privacy:metadata:local_smartsearch_index:keywords'] = 'Additional indexed keywords.';
$string['privacy:metadata:local_smartsearch_index:metadata'] = 'Additional indexed metadata.';
$string['privacy:metadata:local_smartsearch_index:recordid'] = 'The ID of the indexed record.';
$string['privacy:metadata:local_smartsearch_index:recordtype'] = 'The type of indexed record (for example, user or course).';
$string['privacy:metadata:local_smartsearch_index:subtitle'] = 'The indexed subtitle text.';
$string['privacy:metadata:local_smartsearch_index:title'] = 'The indexed title text.';
$string['privacy:metadata:local_smartsearch_index:updatedat'] = 'When the index entry was last updated.';
$string['privacy:metadata:local_smartsearch_log'] = 'Stores anonymous search query logs for analytics purposes.';
$string['privacy:metadata:local_smartsearch_log:clicked_result_id'] = 'ID of the search result clicked, if any.';
$string['privacy:metadata:local_smartsearch_log:query'] = 'The search query (anonymous).';
$string['privacy:metadata:local_smartsearch_log:result_count'] = 'Number of results returned.';
$string['privacy:metadata:local_smartsearch_log:timestamp'] = 'When the search was performed.';
$string['report_analytics_desc'] = 'View and analyze search query logs, including search terms, result counts, click-through rates, and timestamps.';
$string['report_analytics_title'] = 'Smart Search Analytics';
$string['report_chart_searches'] = 'Searches';
$string['report_chart_top_queries'] = 'Most Searched Words';
$string['report_chart_top_queries_title'] = 'Top 10 Search Queries';
$string['report_column_id'] = 'ID';
$string['report_column_query'] = 'Search Query';
$string['report_column_result_count'] = 'Results';
$string['report_column_timestamp'] = 'Date/Time';
$string['report_filter_clicked'] = 'Clicked';
$string['report_filter_end_date'] = 'End Date';
$string['report_filter_query'] = 'Search Query';
$string['report_filter_result_max'] = 'Max Results';
$string['report_filter_result_min'] = 'Min Results';
$string['report_filter_start_date'] = 'Start Date';
$string['report_filters'] = 'Filters';
$string['results_activities'] = 'Activities';
$string['results_categories'] = 'Categories';
$string['results_courses'] = 'Courses';
$string['results_filters_label'] = 'Results from';
$string['results_plugins'] = 'Plugin Pages';
$string['results_settings'] = 'Settings';
$string['results_users'] = 'Users';
$string['resultslabel'] = 'Search results';
$string['roles'] = 'Roles';
$string['search'] = 'Search';
$string['search_results_title'] = 'Search results';
$string['search_user_emails'] = 'Search Email Addresses';
$string['search_user_emails_desc'] = 'Allow email addresses to be searchable (requires appropriate permissions).';
$string['searching'] = 'Searching...';
$string['searchplaceholder'] = 'Search users, courses, activities, settings...';
$string['searchtitle'] = 'Search (Ctrl+K)';
$string['section'] = 'Section';
$string['settings'] = 'Smart Search Settings';
$string['shortcut_close'] = 'Close';
$string['shortcut_esc'] = 'Esc';
$string['shortcut_navigate'] = 'to navigate';
$string['shortcut_select'] = 'to select';
$string['showing_result_count'] = 'Found {$a} result';
$string['showing_results_count'] = 'Found {$a} results';
$string['smartsearch:feedback'] = 'Submit Smart Search feedback';
$string['smartsearch:manage'] = 'Manage Smart Search settings and indexing';
$string['smartsearch:search'] = 'Use Smart Search';
$string['starting_indexing'] = 'Starting indexing...';
$string['task_cleanup'] = 'Smart Search cleanup task';
$string['task_index'] = 'Smart Search indexing task';
$string['testsearch'] = 'Test Search';
$string['view_all_results'] = 'View all results →';
