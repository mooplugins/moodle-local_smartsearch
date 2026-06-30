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
 * @copyright  2025 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {
    var selectedIndex = -1;
    var resultItems = [];
    var observer = null;

    /**
     * Whether the search overlay is currently visible.
     *
     * @return {boolean}
     */
    function isOverlayVisible() {
        var overlay = document.getElementById('smartsearch-overlay');
        if (!overlay) {
            return false;
        }
        return overlay.style.display !== 'none' && overlay.offsetParent !== null;
    }

    /**
     * Initialize keyboard navigation.
     *
     * @param {Function} openCallback Callback to open search
     * @param {Function} closeCallback Callback to close search
     * @param {Function} navigateCallback Callback for navigation
     * @param {Function} selectCallback Callback for selection
     */
    function init(openCallback, closeCallback, navigateCallback, selectCallback) {
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                if (isOverlayVisible()) {
                    closeCallback();
                } else {
                    openCallback();
                    initObserver();
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

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                navigateDown();
                navigateCallback('down');
                return;
            }

            if (e.key === 'ArrowUp') {
                e.preventDefault();
                navigateUp();
                navigateCallback('up');
                return;
            }

            if (e.key === 'Enter' && selectedIndex >= 0) {
                e.preventDefault();
                selectCurrent();
                selectCallback();
            }
        });

        document.addEventListener('smartsearch:resultsUpdated', function() {
            updateResultItems();
        });
    }

    /**
     * Initialize MutationObserver for results container.
     */
    function initObserver() {
        if (observer) {
            observer.disconnect();
        }

        setTimeout(function() {
            var resultsContainer = document.getElementById('smartsearch-results');
            if (resultsContainer) {
                observer = new MutationObserver(function() {
                    updateResultItems();
                });
                observer.observe(resultsContainer, {
                    childList: true,
                    subtree: true
                });
            }
        }, 100);
    }

    /**
     * Update result items list.
     */
    function updateResultItems() {
        var container = document.getElementById('smartsearch-results');
        if (!container) {
            resultItems = [];
            selectedIndex = -1;
            return;
        }
        resultItems = Array.prototype.slice.call(
            container.querySelectorAll('.smartsearch-result-item')
        );
        selectedIndex = -1;
    }

    /**
     * Navigate down.
     */
    function navigateDown() {
        if (selectedIndex < resultItems.length - 1) {
            selectedIndex++;
            updateSelection();
        }
    }

    /**
     * Navigate up.
     */
    function navigateUp() {
        if (selectedIndex > 0) {
            selectedIndex--;
            updateSelection();
        } else if (selectedIndex === 0) {
            selectedIndex = -1;
            updateSelection();
        }
    }

    /**
     * Update visual selection.
     */
    function updateSelection() {
        resultItems.forEach(function(item, index) {
            item.classList.toggle('selected', index === selectedIndex);
        });
    }

    /**
     * Select current item.
     */
    function selectCurrent() {
        if (selectedIndex < 0 || selectedIndex >= resultItems.length) {
            return;
        }
        var item = resultItems[selectedIndex];
        var link = item.querySelector('a');
        if (link && link.href) {
            window.location.href = link.href;
        }
    }

    return {
        init: init
    };
});
