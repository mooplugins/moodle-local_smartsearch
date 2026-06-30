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
 * Settings form for Smart Search.
 *
 * @package    local_smartsearch
 * @copyright  2025 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_smartsearch\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Settings form.
 */
class settings extends \moodleform {
    /**
     * Form definition.
     */
    public function definition() {
        global $PAGE;
        $mform = $this->_form;

        // Enable Smart Search.
        $enabledefault = get_config('local_smartsearch', 'enable');
        $defaultlabel = get_string($enabledefault ? 'yes' : 'no');
        $checkboxlabel = '<span class="text-muted">' .
            get_string('default_value_label', 'local_smartsearch', $defaultlabel) .
            '</span>';
        $enablelabel = get_string('enable', 'local_smartsearch');
        $mform->addElement('advcheckbox', 'local_smartsearch_enable', $enablelabel, $checkboxlabel);
        $mform->addElement('static', 'local_smartsearch_enable_desc', '', get_string('enable_desc', 'local_smartsearch'));
        $mform->setDefault('local_smartsearch_enable', $enabledefault);

        // Category enable/disable.
        $mform->addElement('header', 'categories', get_string('category_categories', 'local_smartsearch'));

        $mform->addElement(
            'advcheckbox',
            'local_smartsearch_enable_users',
            get_string('enable_users', 'local_smartsearch'),
            get_string('enable_users_desc', 'local_smartsearch')
        );
        $mform->setDefault('local_smartsearch_enable_users', get_config('local_smartsearch', 'enable_users', 1));

        $mform->addElement(
            'advcheckbox',
            'local_smartsearch_search_user_emails',
            get_string('search_user_emails', 'local_smartsearch'),
            get_string('search_user_emails_desc', 'local_smartsearch')
        );
        $mform->setDefault('local_smartsearch_search_user_emails', get_config('local_smartsearch', 'search_user_emails', 1));
        $mform->disabledIf('local_smartsearch_search_user_emails', 'local_smartsearch_enable_users');

        $mform->addElement(
            'advcheckbox',
            'local_smartsearch_enable_courses',
            get_string('enable_courses', 'local_smartsearch'),
            get_string('enable_courses_desc', 'local_smartsearch')
        );
        $mform->setDefault('local_smartsearch_enable_courses', get_config('local_smartsearch', 'enable_courses', 1));

        $mform->addElement(
            'advcheckbox',
            'local_smartsearch_enable_activities',
            get_string('enable_activities', 'local_smartsearch'),
            get_string('enable_activities_desc', 'local_smartsearch')
        );
        $mform->setDefault('local_smartsearch_enable_activities', get_config('local_smartsearch', 'enable_activities', 1));

        $mform->addElement(
            'advcheckbox',
            'local_smartsearch_enable_settings',
            get_string('enable_settings', 'local_smartsearch'),
            get_string('enable_settings_desc', 'local_smartsearch')
        );
        $mform->setDefault('local_smartsearch_enable_settings', get_config('local_smartsearch', 'enable_settings', 1));

        $mform->addElement(
            'advcheckbox',
            'local_smartsearch_enable_plugins',
            get_string('enable_plugins', 'local_smartsearch'),
            get_string('enable_plugins_desc', 'local_smartsearch')
        );
        $mform->setDefault('local_smartsearch_enable_plugins', get_config('local_smartsearch', 'enable_plugins', 1));

        $mform->addElement(
            'advcheckbox',
            'local_smartsearch_enable_categories',
            get_string('enable_categories', 'local_smartsearch'),
            get_string('enable_categories_desc', 'local_smartsearch')
        );
        $mform->setDefault('local_smartsearch_enable_categories', get_config('local_smartsearch', 'enable_categories', 1));

        // Note: Role-based visibility removed. Access control is handled by Moodle's capability system.
        // Each search result is filtered based on the user's capabilities
        // (e.g., moodle/user:viewdetails, moodle/course:view, etc.).

        // Analytics.
        $mform->addElement('header', 'analytics', get_string('analytics', 'local_smartsearch'));

        $mform->addElement(
            'advcheckbox',
            'local_smartsearch_enable_analytics',
            get_string('enable_analytics', 'local_smartsearch'),
            get_string('enable_analytics_desc', 'local_smartsearch')
        );
        $mform->setDefault('local_smartsearch_enable_analytics', get_config('local_smartsearch', 'enable_analytics', 1));

        $mform->addElement(
            'text',
            'local_smartsearch_analytics_retention',
            get_string('analytics_retention', 'local_smartsearch'),
            ['size' => 5]
        );
        $mform->setType('local_smartsearch_analytics_retention', PARAM_INT);
        $mform->setDefault('local_smartsearch_analytics_retention', get_config('local_smartsearch', 'analytics_retention', 365));
        $mform->addRule('local_smartsearch_analytics_retention', null, 'numeric', null, 'client');
        $mform->disabledIf('local_smartsearch_analytics_retention', 'local_smartsearch_enable_analytics');
        $mform->addElement(
            'static',
            'local_smartsearch_analytics_retention_desc',
            '',
            \html_writer::span(get_string('analytics_retention_desc', 'local_smartsearch'), 'text-muted')
        );
        if (get_config('local_smartsearch', 'enable_analytics', 1)) {
            $analyticsurl = new \moodle_url('/local/smartsearch/report/');
            $analyticslink = get_string('report_analytics_title', 'local_smartsearch')
                . '<i class="icon fa fa-arrow-right fa-fw"></i>';
            $mform->addElement(
                'static',
                'local_smartsearch_analytics_report',
                '',
                \html_writer::link($analyticsurl, $analyticslink, [
                    'class' => 'mt-2',
                ])
            );
        }

        // Indexing.
        $mform->addElement('header', 'indexing', get_string('indexing', 'local_smartsearch'));

        $lastindexed = (int) get_config('local_smartsearch', 'last_index_time', 0);
        $lastindexedtext = $lastindexed > 0
            ? userdate($lastindexed)
            : get_string('last_indexed_never', 'local_smartsearch');
        $mform->addElement(
            'html',
            \html_writer::tag('p', get_string('last_indexed', 'local_smartsearch', $lastindexedtext), [
                'class' => 'text-muted smartsearch-last-indexed',
                'id' => 'smartsearch-last-indexed',
            ])
        );

        $mform->addElement('html', '<p>' . get_string('indexnow_desc', 'local_smartsearch') . '</p>');
        $indexbutton = '<button type="button" id="smartsearch-index-now" class="btn btn-primary">'
            . get_string('indexnow', 'local_smartsearch') . '</button>';
        $mform->addElement('html', $indexbutton);

        // Progress indicator with warning message.
        $mform->addElement('html', '<div id="smartsearch-index-progress" style="display: none; margin-top: 10px;">' .
            '<div id="smartsearch-index-warning" class="alert alert-warning" role="alert" ' .
                'style="margin-bottom: 10px; display: none;">' .
                '<strong><i class="fa fa-exclamation-triangle" aria-hidden="true"></i> ' .
                    get_string('indexing_warning', 'local_smartsearch') .
                '</strong>' .
                '<div style="margin-top: 5px; font-size: 13px;">' .
                    get_string('indexing_warning_desc', 'local_smartsearch') .
                '</div>' .
            '</div>' .
            '<div class="progress" style="height: 25px;">' .
                '<div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" ' .
                    'style="width: 0%; min-width: 2em;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">' .
                    '<span class="progress-text">0%</span>' .
                '</div>' .
            '</div>' .
            '<div id="smartsearch-index-stats" style="margin-top: 5px; font-size: 12px; color: #666;"></div>' .
        '</div>');

        // Test search.
        $mform->addElement('header', 'testsearch', get_string('testsearch', 'local_smartsearch'));
        $mform->setExpanded('testsearch', false);
        $mform->addElement('text', 'test_query', get_string('search', 'local_smartsearch'), ['size' => 40]);
        $mform->setType('test_query', PARAM_TEXT);
        $mform->addElement('html', '<button type="button" id="smartsearch-test-search" ' .
            'class="btn btn-secondary" style="margin-top: 5px;">' .
            get_string('search', 'local_smartsearch') . '</button>');
        $mform->addElement('html', '<div id="smartsearch-test-results" style="margin-top: 10px; max-height: 300px; '
            . 'overflow-y: auto;"></div>');

        $this->add_action_buttons();
    }
}
