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
 * Result renderer for Smart Search.
 *
 * @module     local_smartsearch/renderer
 * @copyright  2026 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/templates'], function(Templates) {
    var currentQuery = '';

    /**
     * Get category label for a record type.
     *
     * @param {string} recordtype Record type key
     * @return {string} Localised category label
     */
    function getCategoryLabel(recordtype) {
        var labels = {
            'user': M.util.get_string('results_users', 'local_smartsearch'),
            'course': M.util.get_string('results_courses', 'local_smartsearch'),
            'activity': M.util.get_string('results_activities', 'local_smartsearch'),
            'setting': M.util.get_string('results_settings', 'local_smartsearch'),
            'plugin': M.util.get_string('results_plugins', 'local_smartsearch'),
            'category': M.util.get_string('results_categories', 'local_smartsearch')
        };
        return labels[recordtype] || recordtype;
    }

    /**
     * Get Moodle pix icon name for a record type.
     *
     * @param {string} recordtype Record type key
     * @return {string}
     */
    function getTypeIcon(recordtype) {
        var icons = {
            user: 'i/user',
            course: 'i/course',
            activity: 'i/section',
            setting: 'i/settings',
            plugin: 'i/mnethost',
            category: 'i/folder'
        };
        return icons[recordtype] || 'i/search';
    }

    /**
     * Decode HTML entities to plain text.
     *
     * @param {string} str Encoded string
     * @return {string}
     */
    function decodeEntities(str) {
        if (!str || typeof str !== 'string') {
            return '';
        }
        var textarea = document.createElement('textarea');
        textarea.innerHTML = str;
        return textarea.value;
    }

    /**
     * Escape regex special characters.
     *
     * @param {string} str String to escape
     * @return {string} Escaped string
     */
    function escapeRegex(str) {
        if (!str || typeof str !== 'string') {
            return '';
        }
        return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    /**
     * Escape HTML special characters.
     *
     * @param {string} str String to escape
     * @return {string} Escaped string
     */
    function escapeHtml(str) {
        if (!str || typeof str !== 'string') {
            return '';
        }
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    /**
     * Highlight matching text.
     *
     * @param {string} text Text to highlight
     * @param {string} query Query to highlight
     * @return {string} Highlighted text
     */
    function highlightText(text, query) {
        if (!text || typeof text !== 'string') {
            return '';
        }
        if (!query || query.length < 3) {
            return escapeHtml(decodeEntities(text));
        }

        var regex = new RegExp('(' + escapeRegex(query) + ')', 'gi');
        return escapeHtml(decodeEntities(text)).replace(regex, '<mark>$1</mark>');
    }

    /**
     * Normalise subtitle separators for display.
     *
     * @param {string} subtitle Raw subtitle
     * @return {string}
     */
    function formatSubtitle(subtitle) {
        if (!subtitle) {
            return '';
        }
        var text = String(subtitle);
        text = text.replace(/\s*>\s*/g, ' → ');
        // Keep "Type | In: path", convert other pipes to a spaced bullet.
        text = text.replace(/\s*\|\s*(?!In:)/g, '  •  ');
        text = text.replace(/\s*\|\s*/g, ' | ');
        // Drop empty segments and trim dangling separators.
        text = text.split(/\s•\s/).map(function(part) {
            return part.trim();
        }).filter(Boolean).join('  •  ');
        text = text.replace(/(\s→\s|\s\|\s|\s•\s)+$/g, '').trim();
        return text;
    }

    /**
     * Build template context for a single result item.
     *
     * @param {Object} item Result item
     * @param {string} query Search query
     * @return {Object}
     */
    function buildItemContext(item, query) {
        var enrolledlabel = M.util.get_string('enrolledincourses', 'local_smartsearch');
        var subtitle = '';
        if (item.subtitle) {
            subtitle = String(item.subtitle).replace(/\[\[enrolledincourses\]\]/g, enrolledlabel);
            subtitle = formatSubtitle(subtitle);
        } else if (item.contextpath) {
            subtitle = formatSubtitle(String(item.contextpath));
        }

        var isuser = item.recordtype === 'user';
        var initials = item.initials || '';
        if (isuser && !initials) {
            initials = getInitialsFromTitle(item.title || '');
        }

        return {
            id: item.id || '',
            recordid: item.recordid || '',
            recordtype: item.recordtype || '',
            icon: getTypeIcon(item.recordtype),
            typelabel: getCategoryLabel(item.recordtype),
            isuser: isuser,
            haspicture: !!(item.haspicture && item.pictureurl),
            pictureurl: item.pictureurl || '',
            initials: initials,
            url: item.url || '#',
            titlehtml: highlightText(item.title || '', query),
            hassubtitle: !!subtitle,
            subtitlehtml: highlightText(subtitle, query)
        };
    }

    /**
     * Build initials from a result title.
     *
     * @param {string} title Display name
     * @return {string}
     */
    function getInitialsFromTitle(title) {
        var parts = String(title).trim().split(/\s+/).filter(Boolean);
        if (!parts.length) {
            return '';
        }
        if (parts.length === 1) {
            return parts[0].substring(0, 2).toUpperCase();
        }
        return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
    }

    /**
     * Render search results.
     *
     * @param {Object} results Search results object
     * @param {string} query Search query string
     * @return {Promise<string>} HTML string
     */
    function render(results, query) {
        if (query) {
            setQuery(query);
        }

        var categoryPromises = [];

        for (var recordtype in results) {
            if (!results.hasOwnProperty(recordtype)) {
                continue;
            }

            var items = results[recordtype];
            if (!Array.isArray(items) || items.length === 0) {
                continue;
            }

            (function(type, typeItems, searchQuery) {
                var itemPromises = typeItems.map(function(item) {
                    return Templates.renderForPromise(
                        'local_smartsearch/result_item',
                        buildItemContext(item, searchQuery)
                    ).then(function(result) {
                        return result.html;
                    });
                });

                categoryPromises.push(
                    Promise.all(itemPromises).then(function(itemHtml) {
                        return Templates.renderForPromise('local_smartsearch/result_category', {
                            categorylabel: getCategoryLabel(type),
                            itemcount: typeItems.length,
                            itemshtml: itemHtml.join('')
                        });
                    }).then(function(result) {
                        return result.html;
                    })
                );
            })(recordtype, items, currentQuery);
        }

        return Promise.all(categoryPromises).then(function(htmlParts) {
            return htmlParts.join('');
        });
    }

    /**
     * Set current query for highlighting.
     *
     * @param {string} query Query string
     */
    function setQuery(query) {
        currentQuery = query;
    }

    return {
        render: render,
        setQuery: setQuery
    };
});
