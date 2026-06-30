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
 * Settings page JavaScript for Smart Search.
 *
 * @module     local_smartsearch/settings
 * @copyright  2025 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax', 'core/templates', 'core/notification'], function(Ajax, Templates, Notification) {

    /**
     * Fetch JSON from the indexing AJAX endpoint.
     *
     * @param {string} action Action name
     * @return {Promise<Object>}
     */
    function fetchIndexAction(action) {
        var url = M.cfg.wwwroot + '/local/smartsearch/ajax/index.php?' +
            'action=' + encodeURIComponent(action) +
            '&sesskey=' + encodeURIComponent(M.cfg.sesskey);
        return fetch(url, {
            method: 'GET',
            credentials: 'same-origin'
        }).then(function(response) {
            return response.json();
        });
    }

    /**
     * Update progress bar UI.
     *
     * @param {number} percent Progress percent
     */
    function setProgressPercent(percent) {
        var displayPercent = Math.round(percent);
        document.querySelectorAll('.progress-bar').forEach(function(bar) {
            bar.style.width = displayPercent + '%';
            bar.setAttribute('aria-valuenow', String(displayPercent));
        });
        document.querySelectorAll('.progress-text').forEach(function(text) {
            text.textContent = displayPercent + '%';
        });
    }

    /**
     * Hide an element with a short fade.
     *
     * @param {HTMLElement|null} element Element to hide
     */
    function fadeOutElement(element) {
        if (!element) {
            return;
        }
        element.style.transition = 'opacity 500ms';
        element.style.opacity = '0';
        setTimeout(function() {
            element.style.display = 'none';
        }, 500);
    }

    /**
     * Calculate progress percent from indexing stats.
     *
     * @param {Object} stats Indexing stats
     * @param {boolean} inProgress Whether indexing is still running
     * @return {number}
     */
    function calculateTargetPercent(stats, inProgress) {
        var total = stats.total || 0;
        var indexed = stats.indexed || 0;
        var targetPercent = 0;

        if (total > 0) {
            targetPercent = Math.round((indexed / total) * 100);
            if (indexed > 0 && targetPercent === 0) {
                targetPercent = 1;
            }
            targetPercent = Math.min(100, targetPercent);
        } else if (indexed > 0) {
            targetPercent = Math.min(95, Math.max(1, Math.round((indexed / Math.max(10000, indexed * 10)) * 100)));
        } else if (inProgress) {
            targetPercent = 1;
        }

        if (!inProgress && indexed > 0 && total === 0) {
            targetPercent = 100;
        }

        return targetPercent;
    }

    /**
     * Finalise indexing UI when a run completes.
     *
     * @param {Object} progressResponse Progress payload
     * @param {HTMLButtonElement} btn Index button
     * @param {HTMLElement} statsEl Stats element
     * @param {number|null} progressInterval Progress interval id
     * @param {number|null} animationInterval Animation interval id
     * @return {{progressInterval: null, animationInterval: null}}
     */
    function completeIndexingUi(progressResponse, btn, statsEl, progressInterval, animationInterval) {
        if (progressInterval) {
            clearInterval(progressInterval);
            progressInterval = null;
        }
        if (animationInterval) {
            clearInterval(animationInterval);
            animationInterval = null;
        }

        fadeOutElement(document.getElementById('smartsearch-index-warning'));
        btn.disabled = false;
        document.querySelectorAll('.progress-bar').forEach(function(bar) {
            bar.classList.remove('progress-bar-animated');
        });

        var finalPercent = 100;
        if (progressResponse.stats) {
            var finalStats = progressResponse.stats;
            if (finalStats.total > 0) {
                finalPercent = Math.min(100, Math.round((finalStats.indexed / finalStats.total) * 100));
            }
        }
        setProgressPercent(finalPercent);

        if (progressResponse.stats) {
            var completeMsg = M.util.get_string('indexingcomplete', 'local_smartsearch');
            var statsMsg = M.util.get_string('indexed_stats', 'local_smartsearch', {
                indexed: progressResponse.stats.indexed || 0,
                total: progressResponse.stats.total || 0,
                skipped: progressResponse.stats.skipped || 0,
                errors: progressResponse.stats.errors || 0
            });
            statsEl.textContent = completeMsg + ' ' + statsMsg;
            var lastIndexed = document.getElementById('smartsearch-last-indexed');
            if (lastIndexed) {
                lastIndexed.textContent = M.util.get_string(
                    'last_indexed',
                    'local_smartsearch',
                    new Date().toLocaleString()
                );
            }
        } else {
            statsEl.textContent = M.util.get_string('indexingcomplete', 'local_smartsearch');
        }

        return {
            progressInterval: progressInterval,
            animationInterval: animationInterval
        };
    }

    /**
     * Handle indexing progress polling.
     *
     * @param {HTMLButtonElement} btn Index button
     * @param {HTMLElement} statsEl Stats element
     */
    function startProgressPolling(btn, statsEl) {
        var progressInterval = null;
        var animationInterval = null;
        var lastPercent = 0;

        var pollProgress = function() {
            void fetchIndexAction('progress').then(function(progressResponse) {
                if (!progressResponse) {
                    return;
                }

                if (progressResponse.stats) {
                    var stats = progressResponse.stats;
                    var total = stats.total || 0;
                    var indexed = stats.indexed || 0;
                    var targetPercent = calculateTargetPercent(stats, !!progressResponse.in_progress);

                    if (animationInterval) {
                        clearInterval(animationInterval);
                    }

                    var currentPercent = lastPercent;
                    var step = Math.max(1, Math.ceil(Math.abs(targetPercent - currentPercent) / 10));
                    animationInterval = setInterval(function() {
                        if (currentPercent < targetPercent) {
                            currentPercent = Math.min(targetPercent, currentPercent + step);
                        } else if (currentPercent > targetPercent) {
                            currentPercent = Math.max(targetPercent, currentPercent - step);
                        } else {
                            clearInterval(animationInterval);
                            animationInterval = null;
                        }
                        setProgressPercent(currentPercent);
                        lastPercent = currentPercent;
                    }, 50);

                    if (total > 0) {
                        statsEl.textContent = M.util.get_string('indexed_stats', 'local_smartsearch', {
                            indexed: indexed,
                            total: total,
                            skipped: stats.skipped || 0,
                            errors: stats.errors || 0
                        });
                    } else if (progressResponse.in_progress) {
                        statsEl.textContent = M.util.get_string('indexing_in_progress_items', 'local_smartsearch', indexed);
                    }
                } else if (progressResponse.in_progress) {
                    statsEl.textContent = M.util.get_string('starting_indexing', 'local_smartsearch');
                }

                if (!progressResponse.in_progress) {
                    var intervals = completeIndexingUi(
                        progressResponse,
                        btn,
                        statsEl,
                        progressInterval,
                        animationInterval
                    );
                    progressInterval = intervals.progressInterval;
                    animationInterval = intervals.animationInterval;
                }
                return;
            }).catch(function() {
                // Ignore transient polling errors.
            });
        };

        pollProgress();
        progressInterval = setInterval(pollProgress, 1000);
    }

    return {
        init: function() {
            var indexBtn = document.getElementById('smartsearch-index-now');
            if (indexBtn) {
                indexBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    var btn = indexBtn;
                    var progress = document.getElementById('smartsearch-index-progress');
                    var stats = document.getElementById('smartsearch-index-stats');
                    var warning = document.getElementById('smartsearch-index-warning');

                    btn.disabled = true;
                    if (progress) {
                        progress.style.display = 'block';
                    }
                    if (warning) {
                        warning.style.display = 'block';
                        warning.style.opacity = '1';
                    }
                    if (stats) {
                        stats.textContent = M.util.get_string('starting_indexing', 'local_smartsearch');
                    }
                    setProgressPercent(0);

                    fetchIndexAction('start').then(function(response) {
                        if (response && response.status === 'started') {
                            startProgressPolling(btn, stats);
                        } else if (response && response.status === 'already_running') {
                            if (stats) {
                                stats.textContent = M.util.get_string('indexing_already_running', 'local_smartsearch');
                            }
                            startProgressPolling(btn, stats);
                        } else {
                            btn.disabled = false;
                            if (stats) {
                                stats.textContent = M.util.get_string('error_starting_indexing', 'local_smartsearch');
                            }
                        }
                        return undefined;
                    }).catch(function() {
                        btn.disabled = false;
                        if (stats) {
                            stats.textContent = M.util.get_string('error_starting_indexing', 'local_smartsearch');
                        }
                    });
                });
            }

            var testBtn = document.getElementById('smartsearch-test-search');
            if (testBtn) {
                testBtn.addEventListener('click', function() {
                    var queryInput = document.querySelector('input[name="test_query"]');
                    var results = document.getElementById('smartsearch-test-results');
                    var query = queryInput ? queryInput.value.trim() : '';

                    if (!query || query.length < 3) {
                        Notification.addNotification({
                            message: M.util.get_string('minchars', 'local_smartsearch'),
                            type: 'warning'
                        });
                        return undefined;
                    }

                    var showLoading = Promise.resolve();
                    if (results) {
                        showLoading = Templates.renderForPromise('local_smartsearch/loading', {
                            loading: M.util.get_string('searching', 'local_smartsearch')
                        }).then(function(templateResult) {
                            results.innerHTML = templateResult.html;
                            if (templateResult.js) {
                                Templates.runTemplateJS(templateResult.js);
                            }
                            return undefined;
                        });
                    }

                    var request = {
                        methodname: 'local_smartsearch_search',
                        args: {
                            query: query,
                            limit: 10
                        }
                    };

                    var runTestSearch = function() {
                        return Ajax.call([request])[0].then(function(response) {
                            var parsed = JSON.parse(response.results);
                            var categories = [];
                            Object.keys(parsed).forEach(function(category) {
                                if (parsed.hasOwnProperty(category) && Array.isArray(parsed[category])) {
                                    categories.push({
                                        name: category,
                                        count: parsed[category].length
                                    });
                                }
                            });

                            return Templates.renderForPromise('local_smartsearch/test_search_results', {
                                hasresults: categories.length > 0,
                                noresults: M.util.get_string('noresults', 'local_smartsearch'),
                                foundlabel: M.util.get_string('found_results', 'local_smartsearch', categories.length),
                                categories: categories
                            });
                        }).then(function(templateResult) {
                            if (results) {
                                results.innerHTML = templateResult.html;
                                if (templateResult.js) {
                                    Templates.runTemplateJS(templateResult.js);
                                }
                            }
                            return undefined;
                        });
                    };

                    return showLoading.then(runTestSearch).catch(function(error) {
                        if (!results) {
                            return undefined;
                        }
                        var errorMsg = error.message || error.error ||
                            M.util.get_string('error_unknown', 'local_smartsearch');
                        var errorLabel = M.util.get_string('error_label', 'local_smartsearch');
                        results.textContent = errorLabel + ' ' + errorMsg;
                        return undefined;
                    });
                });
            }
        }
    };
});
