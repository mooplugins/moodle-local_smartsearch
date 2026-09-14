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
 * Smart Search feedback modal.
 *
 * @module     local_smartsearch/feedback
 * @author     BitKea Technologies LLP
 * @copyright  2026 BitKea Technologies LLP
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/templates', 'core/ajax', 'core/str'], function(Templates, Ajax, Str) {
    var overlay = null;
    var strings = {};
    var initializing = null;

    /**
     * Load feedback language strings.
     *
     * @return {Promise}
     */
    function loadStrings() {
        var keys = [
            'feedback_title',
            'feedback_description',
            'feedback_type_label',
            'feedback_type_improvement',
            'feedback_type_notworking',
            'feedback_type_missing',
            'feedback_type_irrelevant',
            'feedback_type_other',
            'feedback_message_label',
            'feedback_message_placeholder',
            'feedback_include_tech',
            'feedback_include_tech_help',
            'feedback_privacy_note',
            'feedback_cancel',
            'feedback_submit',
            'feedback_required',
            'feedback_success_title',
            'feedback_success_description',
            'feedback_done',
            'feedback_send_failed',
            'feedback_message_required'
        ];
        return Promise.all(keys.map(function(key) {
            return Str.get_string(key, 'local_smartsearch').then(function(value) {
                strings[key] = value;
                return undefined;
            });
        }));
    }

    /**
     * Ensure the feedback overlay exists in the DOM.
     *
     * @return {Promise}
     */
    function ensureOverlay() {
        if (overlay) {
            return Promise.resolve(overlay);
        }
        if (initializing) {
            return initializing;
        }
        initializing = loadStrings().then(function() {
            var context = {
                title: strings.feedback_title,
                description: strings.feedback_description,
                typelabel: strings.feedback_type_label,
                messageLabel: strings.feedback_message_label,
                messagePlaceholder: strings.feedback_message_placeholder,
                includeTechLabel: strings.feedback_include_tech,
                includeTechHelp: strings.feedback_include_tech_help,
                privacyNote: strings.feedback_privacy_note,
                cancel: strings.feedback_cancel,
                submit: strings.feedback_submit,
                required: strings.feedback_required,
                successTitle: strings.feedback_success_title,
                successDescription: strings.feedback_success_description,
                done: strings.feedback_done,
                types: [
                    {value: 'improvement', label: strings.feedback_type_improvement, selected: true},
                    {value: 'notworking', label: strings.feedback_type_notworking, selected: false},
                    {value: 'missing', label: strings.feedback_type_missing, selected: false},
                    {value: 'irrelevant', label: strings.feedback_type_irrelevant, selected: false},
                    {value: 'other', label: strings.feedback_type_other, selected: false}
                ]
            };
            return Templates.renderForPromise('local_smartsearch/feedback_modal', context);
        }).then(function(result) {
            document.body.insertAdjacentHTML('beforeend', result.html);
            if (result.js) {
                Templates.runTemplateJS(result.js);
            }
            overlay = document.getElementById('smartsearch-feedback-overlay');
            bindOverlayEvents();
            initializing = null;
            return overlay;
        }).catch(function() {
            initializing = null;
            return null;
        });
        return initializing;
    }

    /**
     * Hide feedback overlay.
     */
    function close() {
        if (!overlay) {
            return;
        }
        overlay.style.display = 'none';
        document.body.classList.remove('smartsearch-feedback-open');
    }

    /**
     * Show the form or the success confirmation.
     *
     * @param {string} view 'form' or 'success'
     */
    function setView(view) {
        var isSuccess = view === 'success';
        var title = document.getElementById('smartsearch-feedback-title');
        var intro = document.getElementById('smartsearch-feedback-intro-desc');
        var form = document.getElementById('smartsearch-feedback-form');
        var success = document.getElementById('smartsearch-feedback-success');
        var successTitle = document.getElementById('smartsearch-feedback-success-title');
        if (title) {
            title.hidden = isSuccess;
        }
        if (intro) {
            intro.hidden = isSuccess;
        }
        if (form) {
            form.hidden = isSuccess;
        }
        if (success) {
            success.hidden = !isSuccess;
        }
        if (overlay) {
            overlay.setAttribute('aria-labelledby', isSuccess && successTitle
                ? 'smartsearch-feedback-success-title'
                : 'smartsearch-feedback-title');
        }
    }

    /**
     * Reset form to the entry state.
     */
    function resetForm() {
        var form = document.getElementById('smartsearch-feedback-form');
        var error = document.getElementById('smartsearch-feedback-error');
        setView('form');
        if (form) {
            form.reset();
            var tech = document.getElementById('smartsearch-feedback-tech');
            if (tech) {
                tech.checked = true;
            }
        }
        if (error) {
            error.hidden = true;
            error.textContent = '';
        }
    }

    /**
     * Bind feedback modal events.
     */
    function bindOverlayEvents() {
        if (!overlay || overlay.getAttribute('data-bound') === '1') {
            return;
        }
        overlay.setAttribute('data-bound', '1');

        var closeBtn = document.getElementById('smartsearch-feedback-close');
        var cancelBtn = document.getElementById('smartsearch-feedback-cancel');
        var doneBtn = document.getElementById('smartsearch-feedback-done');
        var form = document.getElementById('smartsearch-feedback-form');

        var closeHandler = function(e) {
            e.preventDefault();
            close();
        };
        if (closeBtn) {
            closeBtn.addEventListener('click', closeHandler);
        }
        if (cancelBtn) {
            cancelBtn.addEventListener('click', closeHandler);
        }
        if (doneBtn) {
            doneBtn.addEventListener('click', closeHandler);
        }

        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) {
                close();
            }
        });

        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                submitFeedback();
            });
        }
    }

    /**
     * Submit feedback through the web service.
     */
    function submitFeedback() {
        var typeEl = document.getElementById('smartsearch-feedback-type');
        var messageEl = document.getElementById('smartsearch-feedback-message');
        var techEl = document.getElementById('smartsearch-feedback-tech');
        var submitBtn = document.getElementById('smartsearch-feedback-submit');
        var error = document.getElementById('smartsearch-feedback-error');

        var message = messageEl ? messageEl.value.trim() : '';
        if (!message) {
            if (error) {
                error.hidden = false;
                error.textContent = strings.feedback_message_required;
            }
            return;
        }

        if (submitBtn) {
            submitBtn.disabled = true;
        }
        if (error) {
            error.hidden = true;
            error.textContent = '';
        }

        var request = {
            methodname: 'local_smartsearch_submit_feedback',
            args: {
                feedbacktype: typeEl ? typeEl.value : 'other',
                message: message,
                includetech: !!(techEl && techEl.checked),
                browser: navigator.userAgent || ''
            }
        };

        Ajax.call([request])[0].then(function() {
            setView('success');
            return undefined;
        }).catch(function() {
            if (error) {
                error.hidden = false;
                error.textContent = strings.feedback_send_failed;
            }
            return undefined;
        }).then(function() {
            if (submitBtn) {
                submitBtn.disabled = false;
            }
            return undefined;
        });
    }

    /**
     * Open the feedback modal.
     *
     * @return {Promise}
     */
    function open() {
        return ensureOverlay().then(function(node) {
            if (!node) {
                return undefined;
            }
            resetForm();
            node.style.display = 'flex';
            document.body.classList.add('smartsearch-feedback-open');
            var message = document.getElementById('smartsearch-feedback-message');
            if (message) {
                setTimeout(function() {
                    message.focus();
                }, 50);
            }
            return undefined;
        });
    }

    return {
        open: open,
        close: close
    };
});
