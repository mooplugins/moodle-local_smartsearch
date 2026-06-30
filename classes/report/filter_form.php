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
 * Filter form for Smart Search analytics report.
 *
 * @package    local_smartsearch
 * @copyright  2025 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_smartsearch\report;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Filter form class for analytics report.
 *
 * @package    local_smartsearch
 * @copyright  2025 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filter_form extends \moodleform {
    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;
        $mform->disable_form_change_checker();

        $mform->addElement('header', 'filterheader', get_string('report_filters', 'local_smartsearch'));

        $mform->addElement('text', 'query', get_string('report_filter_query', 'local_smartsearch'));
        $mform->setType('query', PARAM_TEXT);
        $mform->setDefault('query', '');

        $mform->addElement(
            'date_selector',
            'start_date',
            get_string('report_filter_start_date', 'local_smartsearch'),
            ['optional' => true]
        );
        $mform->addElement(
            'date_selector',
            'end_date',
            get_string('report_filter_end_date', 'local_smartsearch'),
            ['optional' => true]
        );

        $mform->addElement('text', 'result_count_min', get_string('report_filter_result_min', 'local_smartsearch'));
        $mform->setType('result_count_min', PARAM_INT);
        $mform->setDefault('result_count_min', '');

        $mform->addElement('text', 'result_count_max', get_string('report_filter_result_max', 'local_smartsearch'));
        $mform->setType('result_count_max', PARAM_INT);
        $mform->setDefault('result_count_max', '');

        $clickedoptions = [
            '' => get_string('all', 'moodle'),
            '1' => get_string('yes', 'moodle'),
            '0' => get_string('no', 'moodle'),
        ];
        $mform->addElement('select', 'clicked', get_string('report_filter_clicked', 'local_smartsearch'), $clickedoptions);
        $mform->setDefault('clicked', '');

        $buttonarray = [];
        $buttonarray[] = $mform->createElement('submit', 'submitbutton', get_string('filter', 'moodle'));
        $buttonarray[] = $mform->createElement('cancel', 'cancelbutton', get_string('reset', 'moodle'));
        $mform->addGroup($buttonarray, 'buttonar', '', [' '], false);

        $mform->setExpanded('filterheader', false);
    }
}
