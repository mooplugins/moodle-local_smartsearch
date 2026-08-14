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
 * Keyboard navigation for Smart Search.
 *
 * @module     local_smartsearch/keyboard
 * @copyright  2026 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {
    var selectedIndex = -1;
    var resultItems = [];
    var observer = null;

    /**
     * Whether the search overlay is currently open.
     *
     * @return {boolean}
     */
    function isOverlayVisible() {
        return !!(document.body && document.body.classList.contains('smartsearch-open'));
    }

    /**
     * Refresh the list of result nodes without dropping the current selection
     * unless the selected node was removed.
     */
    function updateResultItems() {
        var container = document.getElementById('smartsearch-results');
        if (!container) {
            resultItems = [];
            selectedIndex = -1;
            return;
        }
        var previous = selectedIndex >= 0 ? resultItems[selectedIndex] : null;
        resultItems = Array.prototype.slice.call(
            container.querySelectorAll('.smartsearch-result-item')
        );
        if (previous) {
            selectedIndex = resultItems.indexOf(previous);
        } else if (selectedIndex >= resultItems.length) {
            selectedIndex = -1;
        }
    }

    /**
     * Update visual selection.
     */
    function updateSelection() {
        resultItems.forEach(function(item, index) {
            item.classList.toggle('selected', index === selectedIndex);
            if (index === selectedIndex) {
                item.setAttribute('aria-selected', 'true');
            } else {
                item.removeAttribute('aria-selected');
            }
        });
        if (selectedIndex >= 0 && resultItems[selectedIndex]) {
            resultItems[selectedIndex].scrollIntoView({block: 'nearest'});
        }
    }

    /**
     * Move selection down.
     */
    function navigateDown() {
        updateResultItems();
        if (resultItems.length === 0) {
            return;
        }
        if (selectedIndex < resultItems.length - 1) {
            selectedIndex++;
            updateSelection();
        }
    }

    /**
     * Move selection up.
     */
    function navigateUp() {
        updateResultItems();
        if (resultItems.length === 0) {
            return;
        }
        if (selectedIndex > 0) {
            selectedIndex--;
            updateSelection();
        } else if (selectedIndex === 0) {
            selectedIndex = -1;
            updateSelection();
        }
    }

    /**
     * Select current item.
     *
     * @return {boolean} True if a result was opened
     */
    function selectCurrent() {
        updateResultItems();
        if (selectedIndex < 0 || selectedIndex >= resultItems.length) {
            return false;
        }
        var item = resultItems[selectedIndex];
        var link = item.querySelector('a.smartsearch-result-link') || item.querySelector('a');
        if (link && link.href) {
            window.location.href = link.href;
            return true;
        }
        return false;
    }

    /**
     * Handle overlay keys. Returns true when the event was consumed.
     *
     * @param {KeyboardEvent} e Key event
     * @return {boolean}
     */
    function handleKeys(e) {
        if (!isOverlayVisible()) {
            return false;
        }
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            e.stopPropagation();
            navigateDown();
            return true;
        }
        if (e.key === 'ArrowUp') {
            e.preventDefault();
            e.stopPropagation();
            navigateUp();
            return true;
        }
        if (e.key === 'Enter' && selectedIndex >= 0) {
            e.preventDefault();
            e.stopPropagation();
            selectCurrent();
            return true;
        }
        if (e.key === 'Escape') {
            return false;
        }
        return false;
    }

    /**
     * Initialize MutationObserver for results container.
     */
    function initObserver() {
        if (observer) {
            observer.disconnect();
        }
        var resultsContainer = document.getElementById('smartsearch-results');
        if (!resultsContainer) {
            return;
        }
        observer = new MutationObserver(function() {
            updateResultItems();
        });
        observer.observe(resultsContainer, {
            childList: true,
            subtree: true
        });
        updateResultItems();
    }

    /**
     * Initialize keyboard navigation.
     *
     * @param {Function} openCallback Callback to open search
     * @param {Function} closeCallback Callback to close search
     */
    function init(openCallback, closeCallback) {
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
                e.preventDefault();
                if (isOverlayVisible()) {
                    closeCallback();
                } else {
                    openCallback();
                }
                return;
            }

            if (!isOverlayVisible()) {
                return;
            }

            if (e.key === 'Escape') {
                e.preventDefault();
                closeCallback();
                return;
            }

            handleKeys(e);
        }, true);

        document.addEventListener('smartsearch:resultsUpdated', function() {
            updateResultItems();
        });

        initObserver();
    }

    return {
        init: init,
        handleKeys: handleKeys,
        refresh: updateResultItems
    };
});
