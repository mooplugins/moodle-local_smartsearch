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
 * External API for Smart Search feedback.
 *
 * @package    local_smartsearch
 * @author     BitKea Technologies LLP
 * @copyright  2026 BitKea Technologies LLP
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_smartsearch\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->libdir . '/filelib.php');

/**
 * External feedback API.
 */
class feedback_external extends \external_api {
    /** Default feedback destination email (overridable via $CFG->local_smartsearch_feedback_email). */
    private const FEEDBACK_EMAIL = 'support@mooplugins.com';

    /**
     * Resolve the feedback destination email.
     *
     * @return string
     */
    protected static function get_feedback_email(): string {
        global $CFG;

        if (!empty($CFG->local_smartsearch_feedback_email) && is_string($CFG->local_smartsearch_feedback_email)) {
            $email = trim($CFG->local_smartsearch_feedback_email);
            if ($email !== '' && validate_email($email)) {
                return $email;
            }
        }

        return self::FEEDBACK_EMAIL;
    }

    /**
     * Parameters for submit_feedback.
     *
     * @return \external_function_parameters
     */
    public static function submit_feedback_parameters() {
        return new \external_function_parameters([
            'feedbacktype' => new \external_value(PARAM_ALPHANUMEXT, 'Feedback type key'),
            'message' => new \external_value(PARAM_TEXT, 'Feedback message'),
            'includetech' => new \external_value(PARAM_BOOL, 'Include technical details', VALUE_DEFAULT, true),
            'browser' => new \external_value(PARAM_TEXT, 'Browser user agent', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Return structure for submit_feedback.
     *
     * @return \external_single_structure
     */
    public static function submit_feedback_returns() {
        return new \external_single_structure([
            'success' => new \external_value(PARAM_BOOL, 'Whether the email was sent'),
            'message' => new \external_value(PARAM_TEXT, 'Status message'),
        ]);
    }

    /**
     * Submit product feedback by email.
     *
     * @param string $feedbacktype Feedback type key
     * @param string $message Feedback body
     * @param bool $includetech Include technical details
     * @param string $browser Browser user agent
     * @return array
     */
    public static function submit_feedback(string $feedbacktype, string $message, bool $includetech = true, string $browser = '') {
        global $CFG, $USER, $SITE;

        $params = self::validate_parameters(self::submit_feedback_parameters(), [
            'feedbacktype' => $feedbacktype,
            'message' => $message,
            'includetech' => $includetech,
            'browser' => $browser,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/smartsearch:feedback', $context);

        $message = trim($params['message']);
        if ($message === '') {
            throw new \invalid_parameter_exception(get_string('feedback_message_required', 'local_smartsearch'));
        }

        $typelabels = [
            'improvement' => get_string('feedback_type_improvement', 'local_smartsearch'),
            'notworking' => get_string('feedback_type_notworking', 'local_smartsearch'),
            'missing' => get_string('feedback_type_missing', 'local_smartsearch'),
            'irrelevant' => get_string('feedback_type_irrelevant', 'local_smartsearch'),
            'other' => get_string('feedback_type_other', 'local_smartsearch'),
        ];
        $typelabel = $typelabels[$params['feedbacktype']] ?? $typelabels['other'];

        $rolelabel = is_siteadmin($USER)
            ? get_string('administrator')
            : get_string('user');

        $plugin = new \stdClass();
        include($CFG->dirroot . '/local/smartsearch/version.php');
        $smartsearchversion = $plugin->release ?? (string) ($plugin->version ?? '');

        $fullname = fullname($USER);
        $sitename = format_string($SITE->fullname);
        $browser = '';
        if (!empty($params['includetech'])) {
            $browser = trim($params['browser']);
            if ($browser === '' && !empty($_SERVER['HTTP_USER_AGENT'])) {
                $browser = clean_param($_SERVER['HTTP_USER_AGENT'], PARAM_TEXT);
            }
            if ($browser === '') {
                $browser = 'Unknown';
            }
        }

        $textlines = [
            'Feedback type: ' . $typelabel,
            'Plugin: MooPlugins Smart Search',
            '',
            'Feedback',
            '',
            $message,
            '',
            'Submitted by',
            '',
            'Name: ' . $fullname,
            'Email: ' . $USER->email,
            'Role: ' . $rolelabel,
            '',
            'Moodle site',
            '',
            'Site name: ' . $sitename,
            'Site URL: ' . $CFG->wwwroot,
        ];
        $htmlparts = [
            '<p><strong>Feedback type:</strong> ' . s($typelabel) . '<br>',
            '<strong>Plugin:</strong> MooPlugins Smart Search</p>',
            '<p><strong>Feedback</strong></p>',
            '<p>' . nl2br(s($message), false) . '</p>',
            '<p><strong>Submitted by</strong></p>',
            '<p>Name: ' . s($fullname) . '<br>',
            'Email: ' . s($USER->email) . '<br>',
            'Role: ' . s($rolelabel) . '</p>',
            '<p><strong>Moodle site</strong></p>',
            '<p>Site name: ' . s($sitename) . '<br>',
            'Site URL: ' . s($CFG->wwwroot) . '</p>',
        ];

        if (!empty($params['includetech'])) {
            $textlines = array_merge($textlines, [
                '',
                'Technical details',
                '',
                'Moodle version: ' . $CFG->release,
                'Smart Search version: ' . $smartsearchversion,
                'Browser: ' . $browser,
            ]);
            $htmlparts = array_merge($htmlparts, [
                '<p><strong>Technical details</strong></p>',
                '<p>Moodle version: ' . s($CFG->release) . '<br>',
                'Smart Search version: ' . s($smartsearchversion) . '<br>',
                'Browser: ' . s($browser) . '</p>',
            ]);
        }

        $body = implode("\n", $textlines);
        $bodyhtml = implode("\n", $htmlparts);
        $subject = get_string('feedback_email_subject', 'local_smartsearch', $typelabel);

        $recipient = \core_user::get_noreply_user();
        $recipient->id = -1999;
        $recipient->email = self::get_feedback_email();
        $recipient->firstname = 'MooPlugins';
        $recipient->lastname = 'Support';
        $recipient->maildisplay = true;
        $recipient->mailformat = 1;

        $sent = email_to_user($recipient, $USER, $subject, $body, $bodyhtml);
        if (!$sent) {
            throw new \moodle_exception('feedback_send_failed', 'local_smartsearch');
        }

        return [
            'success' => true,
            'message' => get_string('feedback_success_title', 'local_smartsearch'),
        ];
    }
}
