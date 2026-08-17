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
 * Smart Search main module.
 *
 * @module     local_smartsearch/search
 * @copyright  2026 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/templates', 'core/ajax', 'core/str', 'local_smartsearch/keyboard', 'local_smartsearch/renderer',
    'local_smartsearch/feedback'], function(Templates, Ajax, Str, Keyboard, Renderer, Feedback) {
    var searchOverlay = null;
    var searchInput = null;
    var resultsContainer = null;
    var overlayResultsContainer = null;
    var pageResultsContainer = null;
    var resultsCountElement = null;
    var pageInput = null;
    var pageForm = null;
    var pageFilters = [];
    var paginationContainer = null;
    var pageMode = false;
    var overlayActive = false;
    var isOpen = false;
    var currentQuery = '';
    var searchTimeout = null;
    var strings = {};
    var initialized = false;
    var lastResults = null;
    var pageSize = 10;
    var overlayLimit = 10;
    var currentPage = 1;
    var uiConfig = {
        accentMode: 'default',
        accentColor: '#1C6CF7'
    };

    /**
     * Apply accent CSS variables from admin settings.
     *
     * @param {Object} config
     */
    function applyAccentConfig(config) {
        var mode = config.accentMode || 'default';
        var color = config.accentColor || '#1C6CF7';
        var roots = [];
        var pageRoot = document.querySelector('.smartsearch-page');
        var overlay = document.getElementById('smartsearch-overlay');
        var bar = document.querySelector('.smartsearch-bar');
        if (pageRoot) {
            roots.push(pageRoot);
        }
        if (overlay) {
            roots.push(overlay);
        }
        if (bar) {
            roots.push(bar);
        }

        roots.forEach(function(root) {
            if (!root) {
                return;
            }
            root.classList.add('local-smartsearch');
            root.classList.remove('ss-accent-theme', 'ss-accent-dynamic');
            if (mode === 'theme') {
                root.classList.add('ss-accent-theme', 'ss-accent-dynamic');
                root.style.setProperty('--ss-accent', 'var(--primary, #1c6cf7)');
            } else if (mode === 'custom') {
                root.classList.add('ss-accent-dynamic');
                root.style.setProperty('--ss-accent', color);
            }
        });
    }

    /**
     * Toggle a clear button based on input value.
     *
     * @param {HTMLInputElement} input
     * @param {HTMLElement|null} button
     */
    function syncClearButton(input, button) {
        if (!button || !input) {
            return;
        }
        if (input.value.length > 0) {
            button.removeAttribute('hidden');
        } else {
            button.setAttribute('hidden', 'hidden');
        }
    }

    /**
     * Bind clear control for a search input.
     *
     * @param {HTMLInputElement} input
     * @param {HTMLElement|null} button
     * @param {Function} afterClear
     */
    function bindClearButton(input, button, afterClear) {
        if (!input || !button) {
            return;
        }
        syncClearButton(input, button);
        input.addEventListener('input', function() {
            syncClearButton(input, button);
        });
        button.addEventListener('click', function(e) {
            e.preventDefault();
            input.value = '';
            syncClearButton(input, button);
            input.focus();
            if (typeof afterClear === 'function') {
                afterClear();
            }
        });
    }

    /**
     * Initialize Smart Search.
     *
     * @param {Object} [config] UI config from PHP
     */
    function init(config) {
        // Prevent multiple initializations.
        if (initialized) {
            return;
        }

        uiConfig = {
            accentMode: (config && config.accentMode) ? config.accentMode : 'default',
            accentColor: (config && config.accentColor) ? config.accentColor : '#1C6CF7'
        };
        applyAccentConfig(uiConfig);

        // Load language strings.
        loadStrings().then(function() {
            // Create search overlay.
            return createOverlay();
        }).then(function() {
            // Add search bar to navigation.
            return addSearchBar();
        }).then(function() {
            // Setup keyboard shortcuts.
            Keyboard.init(openSearch, closeSearch);

            // Setup event listeners.
            setupEventListeners();

            // Setup results page UI if applicable.
            initResultsPage();
            applyAccentConfig(uiConfig);

            // Mark as initialized.
            initialized = true;
            return undefined;
        }).catch(function() {
            // Silently fail if initialization error occurs.
        });
    }

    /**
     * Load language strings.
     */
    function loadStrings() {
        var stringKeys = [
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
            'view_all_results',
            'feedback_link',
            'pagination_previous',
            'pagination_next',
            'showing_results_count',
            'showing_result_count'
        ];

        var stringPromises = stringKeys.map(function(key) {
            return Str.get_string(key, 'local_smartsearch').then(function(str) {
                return {key: key, str: str};
            });
        });

        return Promise.all(stringPromises).then(function(stringResults) {
            stringResults.forEach(function(result) {
                strings[result.key] = result.str;
            });
            return undefined;
        });
    }

    /**
     * Check if current page is the results page.
     *
     * @return {boolean}
     */
    function isResultsPage() {
        return document.body && document.body.classList.contains('smartsearch-results-page');
    }

    /**
     * Build results page URL.
     *
     * @param {string} query
     * @param {number} page
     * @return {string}
     */
    function getResultsPageUrl(query, page) {
        var base = (typeof M !== 'undefined' && M.cfg && M.cfg.wwwroot) ? M.cfg.wwwroot : '';
        var url = base + '/local/smartsearch/results.php?q=' + encodeURIComponent(query);
        if (page && page > 1) {
            url += '&page=' + encodeURIComponent(page);
        }
        return url;
    }

    /**
     * Update results page URL without full reload.
     *
     * @param {string} query
     * @param {number} page
     */
    function updateResultsPageUrl(query, page) {
        if (history && typeof history.replaceState === 'function') {
            history.replaceState(null, '', getResultsPageUrl(query, page));
        }
    }

    /**
     * Get query param from URL.
     *
     * @return {string}
     */
    function getQueryFromUrl() {
        if (typeof URLSearchParams === 'undefined') {
            return '';
        }
        var params = new URLSearchParams(window.location.search);
        return params.get('q') || '';
    }

    /**
     * Get the query currently typed in the active search field.
     *
     * @return {string}
     */
    function getActiveInputQuery() {
        if (overlayActive && searchInput) {
            return searchInput.value.trim();
        }
        if (pageMode && !overlayActive && pageInput) {
            return pageInput.value.trim();
        }
        if (searchInput) {
            return searchInput.value.trim();
        }
        return currentQuery;
    }

    /**
     * Get page from URL.
     *
     * @return {number}
     */
    function getPageFromUrl() {
        if (typeof URLSearchParams === 'undefined') {
            return 1;
        }
        var params = new URLSearchParams(window.location.search);
        var page = parseInt(params.get('page'), 10);
        return isNaN(page) || page < 1 ? 1 : page;
    }

    /**
     * Flatten results to an array of items with recordtype.
     *
     * @param {Object} results
     * @return {Array}
     */
    function flattenResults(results) {
        var items = [];
        var seen = {};
        for (var recordtype in results) {
            if (!results.hasOwnProperty(recordtype)) {
                continue;
            }
            var list = results[recordtype] || [];
            if (!Array.isArray(list)) {
                continue;
            }
            var currentType = recordtype;
            for (var i = 0; i < list.length; i++) {
                var item = list[i];
                var copy = Object.assign({}, item);
                copy.recordtype = item.recordtype || currentType;
                var key = copy.recordtype + ':' + (copy.recordid || copy.id || i);
                if (seen[key]) {
                    continue;
                }
                seen[key] = true;
                items.push(copy);
            }
        }
        return items;
    }

    /**
     * Sort items by relevance score if available.
     *
     * @param {Array} items
     * @return {Array}
     */
    function sortItems(items) {
        return items.sort(function(a, b) {
            var aScore = typeof a.relevance_score === 'number' ? a.relevance_score : 0;
            var bScore = typeof b.relevance_score === 'number' ? b.relevance_score : 0;
            return bScore - aScore;
        });
    }

    /**
     * Paginate results.
     *
     * @param {Object} results
     * @param {number} page
     * @param {number} size
     * @return {Object}
     */
    function paginateResults(results, page, size) {
        var items = sortItems(flattenResults(results));
        var total = items.length;
        var start = (page - 1) * size;
        var end = start + size;
        var pageItems = items.slice(start, end);
        var paged = {};
        pageItems.forEach(function(item) {
            var type = item.recordtype || 'other';
            if (!paged[type]) {
                paged[type] = [];
            }
            paged[type].push(item);
        });
        return {
            results: paged,
            total: total
        };
    }

    /**
     * Set View all results link in overlay.
     *
     * @param {number} total
     */
    function updateViewAllLink(total) {
        var viewAll = document.getElementById('smartsearch-view-all');
        var link = viewAll ? viewAll.querySelector('.smartsearch-view-all-link') : null;
        if (!viewAll || !link) {
            return;
        }
        var inputQuery = getActiveInputQuery();
        var show = overlayActive &&
            total > overlayLimit &&
            currentQuery.length >= 3 &&
            inputQuery === currentQuery;
        if (show) {
            link.setAttribute('href', getResultsPageUrl(currentQuery, 1));
            link.textContent = strings.view_all_results;
            viewAll.style.display = 'block';
        } else {
            viewAll.style.display = 'none';
        }
    }

    /**
     * Render pagination controls.
     *
     * @param {number} total
     */
    function renderPagination(total) {
        if (!pageMode || overlayActive || !paginationContainer) {
            return;
        }
        var totalPages = Math.ceil(total / pageSize);
        if (totalPages <= 1) {
            paginationContainer.innerHTML = '';
            return;
        }

        var links = [];
        var prevDisabled = currentPage <= 1;
        links.push({
            page: currentPage - 1,
            label: strings.pagination_previous,
            active: false,
            disabled: prevDisabled
        });

        var start = Math.max(1, currentPage - 2);
        var end = Math.min(totalPages, currentPage + 2);
        if (start > 1) {
            links.push({page: 1, label: '1', active: false, disabled: false});
            if (start > 2) {
                links.push({page: 0, label: '...', active: false, disabled: true});
            }
        }
        for (var i = start; i <= end; i++) {
            links.push({
                page: i,
                label: String(i),
                active: i === currentPage,
                disabled: false
            });
        }
        if (end < totalPages) {
            if (end < totalPages - 1) {
                links.push({page: 0, label: '...', active: false, disabled: true});
            }
            links.push({
                page: totalPages,
                label: String(totalPages),
                active: false,
                disabled: false
            });
        }

        var nextDisabled = currentPage >= totalPages;
        links.push({
            page: currentPage + 1,
            label: strings.pagination_next,
            active: false,
            disabled: nextDisabled
        });

        void Templates.renderForPromise('local_smartsearch/pagination', {links: links}).then(function(result) {
            paginationContainer.innerHTML = result.html;
            if (result.js) {
                Templates.runTemplateJS(result.js);
            }
            return;
        });
    }

    /**
     * Get active filters on results page.
     *
     * @return {string[]}
     */
    function getActiveFilters() {
        if (!pageFilters || pageFilters.length === 0) {
            return [];
        }
        return pageFilters.filter(function(input) {
            return input.checked;
        }).map(function(input) {
            return input.getAttribute('data-recordtype');
        });
    }

    /**
     * Apply filters to results when on results page.
     *
     * @param {Object} results
     * @return {Object}
     */
    function applyFilters(results) {
        if (!pageMode || overlayActive || !pageFilters || pageFilters.length === 0) {
            return results;
        }
        var active = getActiveFilters();
        if (active.length === 0) {
            return {};
        }
        var filtered = {};
        for (var recordtype in results) {
            if (!results.hasOwnProperty(recordtype)) {
                continue;
            }
            filtered[recordtype] = active.indexOf(recordtype) !== -1 ? results[recordtype] : [];
        }
        return filtered;
    }

    /**
     * Initialize results page interactions.
     */
    function initResultsPage() {
        if (!isResultsPage()) {
            return;
        }
        pageMode = true;
        pageInput = document.getElementById('smartsearch-page-input');
        pageResultsContainer = document.getElementById('smartsearch-page-results');
        resultsCountElement = document.getElementById('smartsearch-results-count');
        paginationContainer = document.getElementById('smartsearch-pagination');
        pageForm = document.querySelector('.smartsearch-page-form');
        pageFilters = Array.prototype.slice.call(document.querySelectorAll('.smartsearch-filter-input'));

        if (pageResultsContainer) {
            resultsContainer = pageResultsContainer;
        }
        if (!pageInput || !pageResultsContainer) {
            return;
        }

        var handleSubmit = function() {
            var query = pageInput.value.trim();
            if (query.length < 3) {
                currentPage = 1;
                updateResultsPageUrl(query, currentPage);
                clearResults();
                return;
            }
            currentPage = 1;
            updateResultsPageUrl(query, currentPage);
            debounceSearch(query);
        };

        var query = getQueryFromUrl();
        currentPage = getPageFromUrl();
        if (query) {
            pageInput.value = query;
            debounceSearch(query);
        }

        if (pageForm) {
            pageForm.addEventListener('submit', function(e) {
                e.preventDefault();
                handleSubmit();
            });
        }

        bindClearButton(pageInput, document.getElementById('smartsearch-page-clear'), function() {
            currentPage = 1;
            updateResultsPageUrl('', currentPage);
            clearResults();
        });

        pageInput.addEventListener('input', function() {
            var query = this.value.trim();
            if (query.length >= 3) {
                debounceSearch(query);
            } else {
                clearResults();
            }
        });

        pageInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                handleSubmit();
            }
        });

        pageFilters.forEach(function(filter) {
            filter.addEventListener('change', function() {
                currentPage = 1;
                if (lastResults) {
                    renderResults(lastResults);
                } else if (currentQuery.length >= 3) {
                    performSearch(currentQuery);
                }
            });
        });

        if (paginationContainer) {
            paginationContainer.addEventListener('click', function(e) {
                var target = e.target;
                if (!target.classList.contains('smartsearch-page-link')) {
                    return;
                }
                e.preventDefault();
                var page = parseInt(target.getAttribute('data-page'), 10);
                if (isNaN(page) || page < 1) {
                    return;
                }
                currentPage = page;
                updateResultsPageUrl(currentQuery, currentPage);
                if (lastResults) {
                    renderResults(lastResults);
                }
            });
        }

        bindFeedbackTrigger();
    }

    /**
     * Create search overlay.
     */
    function createOverlay() {
        // Check if overlay already exists to prevent duplicates.
        var existingOverlay = document.getElementById('smartsearch-overlay');
        if (existingOverlay) {
            searchOverlay = existingOverlay;
            searchInput = document.getElementById('smartsearch-input');
            overlayResultsContainer = document.getElementById('smartsearch-results');
            resultsContainer = overlayResultsContainer;
            ensureViewAllContainer();
            bindFeedbackTrigger();
            applyAccentConfig(uiConfig);
            return Promise.resolve();
        }

        var context = {
            searchtitle: strings.searchtitle,
            searchplaceholder: strings.searchplaceholder,
            resultslabel: strings.resultslabel,
            viewall: strings.view_all_results,
            clearsearch: strings.clearsearch,
            shortcut_close: strings.shortcut_close,
            shortcut_esc: strings.shortcut_esc,
            feedback_link: strings.feedback_link
        };

        return Templates.renderForPromise('local_smartsearch/searchoverlay', context).then(function(result) {
            document.body.insertAdjacentHTML('beforeend', result.html);
            if (result.js) {
                Templates.runTemplateJS(result.js);
            }

            searchOverlay = document.getElementById('smartsearch-overlay');
            searchInput = document.getElementById('smartsearch-input');
            overlayResultsContainer = document.getElementById('smartsearch-results');
            resultsContainer = overlayResultsContainer;
            ensureViewAllContainer();
            bindFeedbackTrigger();
            applyAccentConfig(uiConfig);
            return undefined;
        });
    }

    /**
     * Bind Help us improve feedback buttons.
     */
    function bindFeedbackTrigger() {
        var buttons = document.querySelectorAll('.smartsearch-feedback-link');
        buttons.forEach(function(button) {
            if (button.getAttribute('data-listener-attached') === 'true') {
                return;
            }
            button.setAttribute('data-listener-attached', 'true');
            button.addEventListener('click', function(e) {
                e.preventDefault();
                Feedback.open();
            });
        });
    }

    /**
     * Ensure the View all results container exists in the overlay footer.
     */
    function ensureViewAllContainer() {
        if (document.getElementById('smartsearch-view-all')) {
            return;
        }
        var footer = document.querySelector('.smartsearch-footer');
        if (!footer) {
            return;
        }
        var container = document.createElement('div');
        container.className = 'smartsearch-view-all';
        container.id = 'smartsearch-view-all';
        container.style.display = 'none';

        var link = document.createElement('a');
        link.href = '#';
        link.className = 'smartsearch-view-all-link';
        link.textContent = strings.view_all_results;

        container.appendChild(link);
        footer.appendChild(container);
    }

    /**
     * Add search bar to navigation.
     */
    function addSearchBar() {
        // Check if search bar already exists to prevent duplicates.
        var existingBar = document.querySelector('.smartsearch-bar');
        if (existingBar) {
            // Search bar already exists, just setup the click handler if needed.
            var trigger = existingBar.querySelector('.smartsearch-trigger');
            if (trigger && !trigger.hasAttribute('data-listener-attached')) {
                trigger.setAttribute('data-listener-attached', 'true');
                trigger.addEventListener('click', function(e) {
                    e.preventDefault();
                    openSearch();
                });
            }
            return Promise.resolve();
        }

        var context = {
            searchtitle: strings.searchtitle
        };

        return Templates.renderForPromise('local_smartsearch/searchbar', context).then(function(result) {
            // Add to #usernavigation, fallback to navbar or header.
            var userNav = document.getElementById('usernavigation');
            var navbar = document.querySelector('.navbar');
            var header = document.querySelector('header');

            // Check again if search bar was added by another instance while we were loading template.
            var existingBar = document.querySelector('.smartsearch-bar');
            if (existingBar) {
                return;
            }

            if (userNav) {
                userNav.insertAdjacentHTML('afterbegin', result.html);
            } else if (navbar) {
                navbar.insertAdjacentHTML('afterbegin', result.html);
            } else if (header) {
                header.insertAdjacentHTML('afterbegin', result.html);
            }

            if (result.js) {
                Templates.runTemplateJS(result.js);
            }

            // Setup click handler for search trigger.
            var trigger = document.querySelector('.smartsearch-trigger');
            if (trigger) {
                trigger.setAttribute('data-listener-attached', 'true');
                trigger.addEventListener('click', function(e) {
                    e.preventDefault();
                    openSearch();
                });
            }
            applyAccentConfig(uiConfig);
            return;
        });
    }

    /**
     * Setup event listeners.
     */
    function setupEventListeners() {
        if (!searchInput) {
            return;
        }

        searchInput.addEventListener('input', function() {
            var query = this.value.trim();
            if (query.length >= 3) {
                debounceSearch(query);
            } else {
                clearResults();
            }
        });

        searchInput.addEventListener('keydown', function(e) {
            if (Keyboard.handleKeys(e)) {
                return;
            }
            if (e.key === 'Escape') {
                closeSearch();
            }
            if (e.key === 'Enter') {
                var query = this.value.trim();
                if (query.length >= 3) {
                    var hasSelection = resultsContainer &&
                        resultsContainer.querySelector('.smartsearch-result-item.selected');
                    if (!hasSelection) {
                        e.preventDefault();
                        window.location.href = getResultsPageUrl(query, 1);
                    }
                }
            }
        });

        // Close on overlay click.
        if (searchOverlay) {
            searchOverlay.addEventListener('click', function(e) {
                if (e.target.classList.contains('smartsearch-overlay')) {
                    closeSearch();
                }
            });
        }

        bindClearButton(searchInput, document.getElementById('smartsearch-clear'), function() {
            currentQuery = '';
            clearResults();
        });

        var escButton = document.getElementById('smartsearch-esc');
        if (escButton) {
            escButton.addEventListener('click', function(e) {
                e.preventDefault();
                closeSearch();
            });
        }
    }

    /**
     * Debounce search queries.
     *
     * @param {string} query Search query
     */
    function debounceSearch(query) {
        currentQuery = query;
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            performSearch(query);
        }, 500);
    }

    /**
     * Perform search.
     *
     * @param {string} query Search query
     */
    function performSearch(query) {
        if (query.length < 3) {
            clearResults();
            return Promise.resolve();
        }

        showLoading();

        var request = {
            methodname: 'local_smartsearch_search',
            args: {
                query: query,
                limit: pageMode && !overlayActive ? 100 : 50
            },
            fail: function() {
                // Handled in catch; do not show Moodle's exception modal.
            }
        };

        return Ajax.call([request])[0].then(function(response) {
            // Ignore stale responses from an older query or after the input was cleared.
            if (query !== currentQuery || getActiveInputQuery() !== query) {
                return;
            }

            // Check if response exists.
            if (!response) {
                showError(strings.error_search_generic);
                return;
            }

            // Handle different response structures.
            // Moodle AJAX can return: {error: false, data: {results: "..."}} or directly {results: "..."}
            var resultsString = null;

            // Check if response is an array (Moodle sometimes wraps in array).
            if (Array.isArray(response) && response.length > 0) {
                response = response[0];
            }

            // Check for error first.
            if (response.error !== false && response.error) {
                showError(strings.error_search_generic);
                return;
            }

            // Extract results from different possible structures.
            if (response.data && response.data.results) {
                // Structure: {error: false, data: {results: "..."}}
                resultsString = response.data.results;
            } else if (response.results) {
                // Structure: {results: "..."}
                resultsString = response.results;
            } else if (typeof response === 'object' && !response.error) {
                // Response might already be the results object.
                renderResults(response);
                return;
            }

            if (!resultsString) {
                showNoResults();
                return;
            }

            // Parse the JSON results.
            var results;
            try {
                if (typeof resultsString === 'string') {
                    results = JSON.parse(resultsString);
                } else if (typeof resultsString === 'object') {
                    // Results might already be parsed.
                    results = resultsString;
                } else {
                    showNoResults();
                    return;
                }
            } catch (e) {
                showError(strings.error_search_generic);
                return;
            }

            // Check if results is an object and has data.
            if (!results || typeof results !== 'object') {
                showNoResults();
                return;
            }

            renderResults(results);
            return;
        }).catch(function(error) {
            if (window.console && error) {
                window.console.error('Smart Search:', error);
            }
            showError(strings.error_search_generic);
        });
    }

    /**
     * Render search results.
     *
     * @param {Object} results Search results
     */
    function renderResults(results) {
        if (!results || typeof results !== 'object') {
            showNoResults();
            return;
        }

        lastResults = results;
        var filteredResults = applyFilters(results);
        var displayResults = filteredResults;
        var totalCount = flattenResults(filteredResults).length;

        if (pageMode && !overlayActive) {
            var paged = paginateResults(filteredResults, currentPage, pageSize);
            displayResults = paged.results;
            totalCount = paged.total;
            renderPagination(totalCount);
            updateResultsPageUrl(currentQuery, currentPage);
        } else if (overlayActive) {
            var overlayPaged = paginateResults(filteredResults, 1, overlayLimit);
            displayResults = overlayPaged.results;
            totalCount = overlayPaged.total;
            updateViewAllLink(totalCount);
        }

        setResultsCount(totalCount);

        // Check if there are any results with items.
        var hasResults = false;
        for (var key in displayResults) {
            if (displayResults.hasOwnProperty(key) && Array.isArray(displayResults[key]) && displayResults[key].length > 0) {
                hasResults = true;
                break;
            }
        }

        if (!hasResults) {
            if (overlayActive) {
                updateViewAllLink(0);
            }
            if (pageMode && !overlayActive) {
                renderPagination(0);
            }
            showNoResults();
            return;
        }

        var html = Renderer.render(displayResults, currentQuery);
        if (!resultsContainer) {
            resultsContainer = overlayActive ? overlayResultsContainer : pageResultsContainer;
        }
        void Promise.resolve(html).then(function(renderedHtml) {
            if (resultsContainer) {
                resultsContainer.innerHTML = renderedHtml;
                document.dispatchEvent(new CustomEvent('smartsearch:resultsUpdated'));
            } else {
                showError(strings.error_results_container);
            }
            return undefined;
        }).catch(function() {
            // Ignore render errors; showError may already have run.
        });
    }

    /**
     * Show loading state.
     */
    function showLoading() {
        setResultsCount('');
        var context = {
            loading: strings.loading
        };

        return Templates.renderForPromise('local_smartsearch/loading', context).then(function(result) {
            if (resultsContainer) {
                resultsContainer.innerHTML = result.html;
                if (result.js) {
                    Templates.runTemplateJS(result.js);
                }
            }
            return undefined;
        }).catch(function() {
            // Ignore template render errors during loading state.
        });
    }

    /**
     * Show no results.
     */
    function showNoResults() {
        setResultsCount('');
        var context = {
            noresults: strings.noresults
        };

        return Templates.renderForPromise('local_smartsearch/noresults', context).then(function(result) {
            if (resultsContainer) {
                resultsContainer.innerHTML = result.html;
                if (result.js) {
                    Templates.runTemplateJS(result.js);
                }
            }
            if (overlayActive) {
                updateViewAllLink(0);
            }
            if (pageMode && !overlayActive) {
                renderPagination(0);
            }
            return undefined;
        }).catch(function() {
            // Ignore template render errors for empty state.
        });
    }

    /**
     * Show error.
     *
     * @param {string} message Error message
     */
    function showError(message) {
        setResultsCount('');
        var context = {
            message: message || strings.error_search_generic
        };

        return Templates.renderForPromise('local_smartsearch/error', context).then(function(result) {
            if (resultsContainer) {
                resultsContainer.innerHTML = result.html;
                if (result.js) {
                    Templates.runTemplateJS(result.js);
                }
            }
            return undefined;
        }).catch(function() {
            // Ignore template render errors for error state.
        });
    }

    /**
     * Clear results.
     */
    function clearResults() {
        if (resultsContainer) {
            resultsContainer.innerHTML = '';
        }
        setResultsCount('');
        updateViewAllLink(0);
        if (pageMode && !overlayActive) {
            renderPagination(0);
        }
        lastResults = null;
    }

    /**
     * Update results count text on results page.
     *
     * @param {number|string} count
     */
    function setResultsCount(count) {
        if (!pageMode || overlayActive || !resultsCountElement) {
            return;
        }
        if (count === null || typeof count === 'undefined' || count === '') {
            resultsCountElement.textContent = '';
            return;
        }
        var label = (count === 1)
            ? strings.showing_result_count
            : strings.showing_results_count;
        resultsCountElement.textContent = label.replace('{$a}', count);
    }

    /**
     * Open search overlay.
     */
    function openSearch() {
        if (isOpen || !searchOverlay) {
            return;
        }

        isOpen = true;
        overlayActive = true;
        clearTimeout(searchTimeout);
        currentQuery = '';
        if (searchInput) {
            searchInput.value = '';
        }
        if (overlayResultsContainer) {
            resultsContainer = overlayResultsContainer;
        }
        clearResults();
        searchOverlay.style.display = 'flex';
        searchOverlay.style.opacity = '0';

        // Fade in animation.
        requestAnimationFrame(function() {
            searchOverlay.style.transition = 'opacity 200ms';
            searchOverlay.style.opacity = '1';
        });

        if (searchInput) {
            setTimeout(function() {
                searchInput.focus();
            }, 100);
        }

        document.body.classList.add('smartsearch-open');
    }

    /**
     * Close search overlay.
     */
    function closeSearch() {
        if (!isOpen || !searchOverlay) {
            return;
        }

        isOpen = false;
        overlayActive = false;
        clearTimeout(searchTimeout);
        currentQuery = '';
        if (searchInput) {
            searchInput.value = '';
        }
        clearResults();

        // Fade out animation.
        searchOverlay.style.transition = 'opacity 200ms';
        searchOverlay.style.opacity = '0';

        setTimeout(function() {
            searchOverlay.style.display = 'none';
            document.body.classList.remove('smartsearch-open');
            if (pageMode && pageResultsContainer) {
                resultsContainer = pageResultsContainer;
            }
        }, 200);
    }

    return {
        init: init,
        open: openSearch,
        close: closeSearch
    };
});
