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
 * Query class for Smart Search.
 *
 * @package    local_smartsearch
 * @copyright  2025 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_smartsearch;


/**
 * Handles search queries and ranking.
 */
class query {
    /**
     * Perform a search query.
     *
     * @param string $query The search query
     * @param int $userid The user performing the search
     * @param int $limit Maximum number of results per category
     * @return array<string, array> Categorized results, keyed by record type
     */
    public static function search(string $query, int $userid, int $limit = 50): array {
        global $DB;

        if (strlen(trim($query)) < 3) {
            return [];
        }

        permissions::begin_search_context();

        $query = trim($query);
        $results = [];

        // Get all enabled search areas for this user.
        $searchareas = self::get_accessible_search_areas($userid);

        if (empty($searchareas)) {
            return [];
        }

        foreach ($searchareas as $recordtype => $area) {
            // Areas are already filtered by is_enabled() in get_accessible_search_areas().
            // Capability-based filtering is handled per-item in search_area() method.
            // No need for role-based visibility - capabilities handle access control.

            // Perform search for this area.
            $arearesults = self::search_area($query, $recordtype, $userid, $limit);

            // Skip if only debug info was returned (error case).
            if (
                is_array($arearesults)
                && (isset($arearesults['_debug_only']) || isset($arearesults['_debug']))
                && count($arearesults) === 1
            ) {
                continue;
            }

            if (!empty($arearesults)) {
                $results[$recordtype] = $arearesults;
            }
        }

        // Log search for analytics (anonymous).
        if (get_config('local_smartsearch', 'enable_analytics')) {
            analytics::log_search($query, array_sum(array_map('count', $results)));
        }

        return $results;
    }


    /**
     * Search within a specific area.
     *
     * @param string $query The search query
     * @param string $recordtype The record type to search
     * @param int $userid The user performing the search
     * @param int $limit Maximum number of results
     * @return array<\stdClass> Array of result objects
     */
    protected static function search_area(string $query, string $recordtype, int $userid, int $limit): array {
        global $DB;

        // Check if table exists.
        $dbman = $DB->get_manager();
        $table = new \xmldb_table('local_smartsearch_index');
        if (!$dbman->table_exists($table)) {
            // Table doesn't exist yet, return empty.
            return [];
        }

        // Use LIKE search (more reliable, works without FULLTEXT index).
        $searchterms = self::prepare_search_terms($query);
        if (empty($searchterms)) {
            return [];
        }

        // Build search conditions - search for any term in any field.
        $fieldconditions = [];
        $params = [];

        // Build conditions for each search term using LIKE (works on all databases).
        // For each term, we want to match it in title only (not subtitle to avoid false matches).
        // Keywords are searched in PHP fallback.
        // So for term "cat", we want: (title LIKE '%cat%')
        // For multiple terms, we want: (term1 matches title) OR (term2 matches title).
        $termconditions = [];
        $termparams = [];

        foreach ($searchterms as $term) {
            // For each term, create conditions for title and keywords only.
            // Exclude subtitle from search to avoid false matches (e.g., "cat" matching "courses").
            // Subtitle is for display purposes only.
            $termfieldconditions = [];
            // Use case-insensitive matching so queries like "jane" match "Jane".
            $termfieldconditions[] = "LOWER(title) LIKE LOWER(?)";
            $termfieldconditions[] = "LOWER(keywords) LIKE LOWER(?)";
            // Search in keywords (stored as JSON array in TEXT column).
            $termparams[] = "%{$term}%";
            $termparams[] = "%{$term}%";

            // Group the field conditions for this term with OR.
            $termconditions[] = "(" . implode(' OR ', $termfieldconditions) . ")";
        }

        // Combine all term conditions with AND (all terms must match).
        $fieldconditions = $termconditions;
        $params = $termparams;

        // Safety check: if no field conditions, return empty.
        if (empty($fieldconditions)) {
            return [];
        }

        // All terms must match in at least one field (AND between all conditions).
        // Build the SQL query - use AND between all field conditions for each term.
        // Select specific columns instead of * to avoid issues with TEXT columns.
        // Order by updatedat first, then we'll sort by relevance in PHP to avoid database compatibility issues.
        $safelimit = max(1, (int)$limit);
        $sql = "SELECT id, recordtype, recordid, contextpath, title, subtitle, keywords, url, metadata, updatedat, relevance_score
                FROM {local_smartsearch_index}
                WHERE recordtype = ?
                AND (" . implode(' AND ', $fieldconditions) . ")
                ORDER BY updatedat DESC
                LIMIT {$safelimit}";

        $params = array_merge([$recordtype], $params);

        try {
            // First, check if table exists.
            $dbman = $DB->get_manager();
            if (!$dbman->table_exists('local_smartsearch_index')) {
                return [];
            }

            // Try the LIKE query first. If it fails, fall back to PHP filtering.
            $records = false;
            try {
                $records = $DB->get_records_sql($sql, $params);
            } catch (\Exception $likeerror) {
                // LIKE query failed, fall back to PHP filtering.
                $records = false;
            }

            // If LIKE query failed, use PHP filtering fallback.
            if ($records === false) {
                // Fetch records using get_records() and filter in PHP.
                $allrecords = $DB->get_records(
                    'local_smartsearch_index',
                    ['recordtype' => $recordtype],
                    'updatedat DESC',
                    '*',
                    0,
                    $limit * 10
                );
                // Filter records in PHP based on search terms.
                $records = [];
                if (is_array($allrecords)) {
                    foreach ($allrecords as $record) {
                        $matches = true;
                        foreach ($searchterms as $term) {
                            // Search in title and keywords only (not subtitle to avoid false matches).
                            // Use simple substring match - since we're not searching subtitle,
                            // we don't need complex word boundary matching.
                            $title = $record->title ?? '';
                            $titlematch = stripos($title, $term) !== false;

                            $keywordsmatch = false;
                            // Keywords is stored as JSON array, so decode and search.
                            if (!empty($record->keywords)) {
                                $keywords = json_decode($record->keywords, true);
                                if (is_array($keywords)) {
                                    $keywordsstr = implode(' ', $keywords);
                                    $keywordsmatch = stripos($keywordsstr, $term) !== false;
                                } else {
                                    // If not JSON, treat as string.
                                    $keywordsmatch = stripos($record->keywords, $term) !== false;
                                }
                            }
                            if (!$titlematch && !$keywordsmatch) {
                                $matches = false;
                                break;
                            }
                        }
                        if ($matches) {
                            $records[] = $record;
                        }
                        if (count($records) >= $limit) {
                            break;
                        }
                    }
                }
            }
        } catch (\dml_exception $e) {
            debugging('Smart Search query error: ' . $e->getMessage() . ' SQL: ' . $sql .
                ' Params: ' . json_encode($params), DEBUG_NORMAL);
            return [];
        } catch (\Exception $e) {
            debugging('Smart Search query error: ' . $e->getMessage(), DEBUG_NORMAL);
            return [];
        }

        // Filter by permissions and add relevance scores.
        $results = [];
        $area = self::get_search_area_by_type($recordtype);

        foreach ($records as $record) {
            // Double-check that the record actually matches the search query in title or keywords.
            // This is a safety check in case the SQL query or PHP fallback didn't filter correctly.
            // Use word boundary matching to avoid partial matches (e.g., "cat" matching "category" or "courses").
            $matchessearch = true;
            $title = $record->title ?? '';
            $keywordsstr = '';
            if (!empty($record->keywords)) {
                $keywords = json_decode($record->keywords, true);
                if (is_array($keywords)) {
                    $keywordsstr = implode(' ', $keywords);
                } else {
                    $keywordsstr = $record->keywords;
                }
            }

            $titlematched = false;
            $keywordsmatched = false;

            foreach ($searchterms as $term) {
                // Check title - use simple substring match since SQL already filtered by title LIKE.
                // The SQL query uses LIKE '%cat%' which will match "cat" anywhere in title.
                // We just need to verify the match exists (safety check).
                // For keywords, we need to check since they're not in the SQL query.
                $titlematch = stripos($title, $term) !== false;

                // Check keywords - use substring match (keywords are searched in PHP fallback).
                $keywordsmatch = false;
                if (!empty($keywordsstr)) {
                    $keywordsmatch = stripos($keywordsstr, $term) !== false;
                }

                if ($titlematch) {
                    $titlematched = true;
                }
                if ($keywordsmatch) {
                    $keywordsmatched = true;
                }

                if (!$titlematch && !$keywordsmatch) {
                    $matchessearch = false;
                    break;
                }
            }
            // Skip records that don't actually match (safety check).
            if (!$matchessearch) {
                continue;
            }

            // Filter user email-only matches when:
            // - email search is disabled in plugin settings, OR
            // - current user is not allowed to view email addresses.
            if (
                $recordtype === 'user'
                    && (!get_config('local_smartsearch', 'search_user_emails')
                    || !permissions::can_view_emails($userid))
            ) {
                // If the match is only in keywords and not in title, check if it's an email match.
                if ($keywordsmatched && !$titlematched) {
                    // Get metadata to check if the match is on email.
                    $metadata = null;
                    if (!empty($record->metadata)) {
                        if (is_string($record->metadata)) {
                            $metadata = json_decode($record->metadata, true);
                        } else if (is_object($record->metadata)) {
                            $metadata = (array) $record->metadata;
                        } else if (is_array($record->metadata)) {
                            $metadata = $record->metadata;
                        }
                    }

                    // Check if any search term matches the email in metadata.
                    $emailmatch = true;
                    if ($metadata && isset($metadata['email'])) {
                        $email = $metadata['email'] ?? '';
                        foreach ($searchterms as $term) {
                            if (stripos($email, $term) === false) {
                                $emailmatch = false;
                                break;
                            }
                        }
                    } else {
                        $emailmatch = false;
                    }

                    // If the match is on email (not username), filter it out.
                    if ($emailmatch) {
                        continue;
                    }
                    // If we can't determine (metadata not available), be conservative and allow it
                    // (it might be a username match, which should always be searchable).
                }
            }

            // Check permissions for all record types (including settings and plugins).
            // The can_index method for each type will determine if the user can view it.
            if ($area) {
                $canindex = $area->can_index($userid, $record->recordid);
                if (!$canindex) {
                    continue;
                }
            }

            // Calculate relevance score.
            // Ensure query is a string (could be array in some edge cases).
            if (is_string($query)) {
                $querystring = $query;
            } else if (is_array($query)) {
                $querystring = implode(' ', $query);
            } else {
                $querystring = (string) $query;
            }
            $record->relevance_score = self::calculate_relevance($querystring, $record);

            // Get item URL and actions.
            if ($area) {
                $record->url = self::resolve_result_url($record, $area, $userid);
            } else {
                $record->url = new \moodle_url('/');
            }

            if (!permissions::is_usable_result_url($record->url ?? '')) {
                continue;
            }

            if (!permissions::can_access_search_result_url($userid, $record->url ?? '')) {
                continue;
            }

            if ($area) {
                $record->actions = self::filter_item_actions(
                    $area->get_item_actions($record->recordid, $userid),
                    $record->url,
                    $userid
                );
            } else {
                $record->actions = [];
            }

            $results[] = $record;
        }

        // Sort by relevance score.
        usort($results, function ($a, $b) {
            return $b->relevance_score <=> $a->relevance_score;
        });

        $finalresults = array_slice($results, 0, $limit);

        // Filter out any debug info arrays that might have been accidentally included.
        $finalresults = array_filter($finalresults, function ($item) {
            // If it's an array with debug info keys, exclude it.
            if (is_array($item) && isset($item['recordtype']) && isset($item['search_terms']) && isset($item['sql'])) {
                return false;
            }
            // If it's an object, check if it has debug-like properties.
            if (is_object($item) && isset($item->recordtype) && isset($item->search_terms) && isset($item->sql)) {
                return false;
            }
            return true;
        });
        // Re-index array after filtering.
        $finalresults = array_values($finalresults);

        return $finalresults;
    }

    /**
     * Calculate relevance score for a result.
     *
     * @param string $query The search query
     * @param object $record The index record
     * @return int Relevance score
     */
    protected static function calculate_relevance(string $query, object $record): int {
        $score = 0;
        $querylower = mb_strtolower($query);

        // Ensure title is a string (could be array or other type in some edge cases).
        $title = $record->title ?? '';
        if (is_array($title)) {
            $title = implode(' ', $title);
        } else if (!is_string($title)) {
            $title = (string) $title;
        }
        $titlelower = mb_strtolower($title);

        // Subtitle is not used in relevance calculation, but ensure it's a string if present.
        $subtitle = $record->subtitle ?? '';
        if (is_array($subtitle)) {
            $subtitle = implode(' ', $subtitle);
        } else if (!is_string($subtitle)) {
            $subtitle = (string) $subtitle;
        }
        $subtitlelower = mb_strtolower($subtitle);

        // Expand query with synonyms.
        $expandedterms = self::expand_with_synonyms($querylower);

        // Check for matches in title.
        $titlematched = false;
        foreach ($expandedterms as $term) {
            if ($titlelower === $term) {
                // Exact title match: 10 points.
                $score += 10;
                $titlematched = true;
                break;
            } else if (strpos($titlelower, $term) !== false) {
                // Partial title match: 8 points.
                $score += 8;
                $titlematched = true;
                break;
            }
        }

        // Subtitle is excluded from search to avoid false matches (e.g., "cat" matching "courses").
        // We don't check subtitle for relevance scoring either.

        // Check keywords.
        if (!empty($record->keywords)) {
            $keywords = json_decode($record->keywords, true);
            if (is_array($keywords)) {
                foreach ($keywords as $keyword) {
                    // Ensure keyword is a string (could be array or other type).
                    if (is_array($keyword)) {
                        $keyword = implode(' ', $keyword);
                    } else if (!is_string($keyword)) {
                        $keyword = (string) $keyword;
                    }
                    if (empty($keyword)) {
                        continue;
                    }
                    $keywordlower = mb_strtolower($keyword);
                    foreach ($expandedterms as $term) {
                        if (stripos($keywordlower, $term) !== false) {
                            // Match in keywords: 4 points.
                            $score += 4;
                            break 2;
                        }
                    }
                }
            }
        }

        // Fuzzy matching: check Levenshtein distance if no exact match found (title only, not subtitle).
        if ($score === 0) {
            $fuzzyscore = self::calculate_fuzzy_match($querylower, $titlelower, '');
            if ($fuzzyscore > 0) {
                $score += $fuzzyscore;
            }
        }

        // Recency bonus: +1 point (items updated in last 7 days).
        if ($record->updatedat > (time() - 604800)) {
            $score += 1;
        }

        return $score;
    }

    /**
     * Expand search terms with synonyms.
     *
     * @param string $query The search query
     * @return array Array of expanded terms
     */
    protected static function expand_with_synonyms(string $query): array {
        $terms = [$query];

        // Built-in synonym dictionary.
        $synonyms = self::get_synonyms();

        $querywords = explode(' ', $query);
        foreach ($querywords as $word) {
            $word = trim($word);
            if (isset($synonyms[$word])) {
                $terms = array_merge($terms, $synonyms[$word]);
            }
        }

        // Check for configurable synonyms.
        $configsynonyms = get_config('local_smartsearch', 'synonyms');
        if (!empty($configsynonyms)) {
            $customsynonyms = json_decode($configsynonyms, true);
            if (is_array($customsynonyms)) {
                foreach ($querywords as $word) {
                    $word = trim($word);
                    if (isset($customsynonyms[$word])) {
                        $terms = array_merge($terms, $customsynonyms[$word]);
                    }
                }
            }
        }

        return array_unique($terms);
    }

    /**
     * Get built-in synonym dictionary.
     *
     * @return array Synonym dictionary
     */
    protected static function get_synonyms(): array {
        return [
            'email' => ['smtp', 'mail', 'e-mail'],
            'user' => ['account', 'profile'],
            'course' => ['class', 'subject'],
            'activity' => ['module', 'resource'],
            'setting' => ['config', 'configuration'],
            'plugin' => ['addon', 'extension'],
        ];
    }

    /**
     * Calculate fuzzy match score using Levenshtein distance.
     *
     * @param string $query The search query
     * @param string $title The title to match against
     * @param string $subtitle The subtitle to match against
     * @return int Fuzzy match score (0-2)
     */
    protected static function calculate_fuzzy_match(string $query, string $title, string $subtitle): int {
        $maxdistance = max(1, floor(strlen($query) * 0.3)); // Allow 30% character difference.
        $querywords = explode(' ', $query);

        // Check title.
        $titlewords = explode(' ', $title);
        foreach ($querywords as $qword) {
            foreach ($titlewords as $tword) {
                if (strlen($qword) >= 3 && strlen($tword) >= 3) {
                    $distance = levenshtein($qword, $tword);
                    if ($distance <= $maxdistance && $distance < strlen($qword)) {
                        return 2; // Fuzzy match found.
                    }
                }
            }
        }

        // Subtitle is excluded from search to avoid false matches.
        // No subtitle checking in fuzzy matching.

        return 0;
    }

    /**
     * Prepare search terms from query string.
     *
     * @param string $query The search query
     * @return array Array of search terms
     */
    protected static function prepare_search_terms(string $query): array {
        // Split query into words and clean them.
        $terms = preg_split('/\s+/', trim($query));
        $terms = array_filter($terms, function ($term) {
            return strlen($term) >= 3;
        });
        return array_values($terms);
    }

    /**
     * Get accessible search areas for a user.
     *
     * @param int $userid The user ID
     * @return array<string, \local_smartsearch\search_area\base> Array of search area instances keyed by record type
     */
    protected static function get_accessible_search_areas(int $userid): array {
        $areas = [];
        $areaclasses = [
            'user' => \local_smartsearch\search_area\user::class,
            'course' => \local_smartsearch\search_area\course::class,
            'activity' => \local_smartsearch\search_area\activity::class,
            'setting' => \local_smartsearch\search_area\setting::class,
            'plugin' => \local_smartsearch\search_area\plugin::class,
            'category' => \local_smartsearch\search_area\category::class,
        ];

        foreach ($areaclasses as $type => $class) {
            if (class_exists($class)) {
                $area = new $class();
                if ($area->is_enabled()) {
                    $areas[$type] = $area;
                }
            }
        }

        // Also include registered plugin areas.
        $registered = \local_smartsearch\api::get_registered_areas();
        foreach ($registered as $registeredarea) {
            if ($registeredarea->is_enabled()) {
                $areas[$registeredarea->get_record_type()] = $registeredarea;
            }
        }

        return $areas;
    }

    /**
     * Get user roles.
     *
     * @param int $userid The user ID
     * @return array<int> Array of role IDs
     */
    protected static function get_user_roles(int $userid): array {
        global $DB;
        $roles = $DB->get_records_sql(
            "SELECT DISTINCT r.id
             FROM {role} r
             JOIN {role_assignments} ra ON ra.roleid = r.id
             WHERE ra.userid = ?",
            [$userid]
        );
        return array_keys($roles);
    }

    /**
     * Get a search area by record type.
     *
     * @param string $recordtype The record type
     * @return \local_smartsearch\search_area\base|null Search area instance or null if not found
     */
    protected static function get_search_area_by_type(string $recordtype): ?\local_smartsearch\search_area\base {
        $areaclasses = [
            'user' => \local_smartsearch\search_area\user::class,
            'course' => \local_smartsearch\search_area\course::class,
            'activity' => \local_smartsearch\search_area\activity::class,
            'setting' => \local_smartsearch\search_area\setting::class,
            'plugin' => \local_smartsearch\search_area\plugin::class,
            'category' => \local_smartsearch\search_area\category::class,
        ];

        if (isset($areaclasses[$recordtype]) && class_exists($areaclasses[$recordtype])) {
            return new $areaclasses[$recordtype]();
        }

        // Check registered areas.
        $registered = \local_smartsearch\api::get_registered_areas();
        foreach ($registered as $area) {
            if ($area->get_record_type() === $recordtype) {
                return $area;
            }
        }

        return null;
    }

    /**
     * Resolve the best URL for a search result, preferring the indexed URL when safe.
     *
     * @param \stdClass $record Index row
     * @param \local_smartsearch\search_area\base $area Search area
     * @param int $userid Viewer user id
     * @return \moodle_url
     */
    protected static function resolve_result_url(
        \stdClass $record,
        \local_smartsearch\search_area\base $area,
        int $userid
    ): \moodle_url {
        if ($record->recordtype === 'user') {
            return permissions::get_user_result_url($userid, (int) $record->recordid);
        }

        if (
            $record->recordtype === 'category'
            && permissions::can_access_courses_management_page($userid)
        ) {
            return new \moodle_url('/courses.php', ['categoryid' => (int) $record->recordid]);
        }

        if (!empty($record->url) && is_string($record->url)) {
            $parsed = self::parse_stored_url($record->url);
            if ($parsed !== null && permissions::is_usable_result_url($parsed)) {
                return $parsed;
            }
        }

        $itemurl = $area->get_item_url($record->recordid);
        if ($itemurl !== null && permissions::is_usable_result_url($itemurl)) {
            return $itemurl;
        }

        return new \moodle_url('/');
    }

    /**
     * Keep only action links that are complete, permitted, and not duplicates of the main result URL.
     *
     * @param array $actions Raw actions from the search area
     * @param \moodle_url|string|null $resulturl Resolved result URL
     * @param int $userid Viewer user id
     * @return array
     */
    protected static function filter_item_actions(array $actions, $resulturl, int $userid): array {
        $resultstr = '';
        if ($resulturl instanceof \moodle_url) {
            $resultstr = $resulturl->out(false);
        } else if (is_string($resulturl)) {
            $parsedresult = self::parse_stored_url($resulturl);
            $resultstr = $parsedresult ? $parsedresult->out(false) : $resulturl;
        }

        $filtered = [];
        foreach ($actions as $action) {
            if (!is_array($action)) {
                continue;
            }

            $actionurl = $action['url'] ?? null;
            if (!($actionurl instanceof \moodle_url)) {
                if (!is_string($actionurl) || $actionurl === '') {
                    continue;
                }
                $actionurl = self::parse_stored_url($actionurl);
                if ($actionurl === null) {
                    continue;
                }
            }

            if (!permissions::is_usable_result_url($actionurl)) {
                continue;
            }
            if (!permissions::can_access_search_result_url($userid, $actionurl)) {
                continue;
            }

            $actionstr = $actionurl->out(false);
            if ($resultstr !== '' && $actionstr === $resultstr) {
                continue;
            }

            $action['url'] = $actionurl;
            $filtered[] = $action;
        }

        return $filtered;
    }

    /**
     * Parse a relative URL stored in the search index.
     *
     * @param string $urlstring Stored URL
     * @return \moodle_url|null
     */
    public static function parse_stored_url(string $urlstring): ?\moodle_url {
        global $CFG;

        try {
            if (preg_match('#^https?://[^/]+(.*)$#', $urlstring, $matches)) {
                $urlstring = $matches[1];
            }

            if (!empty($CFG->wwwroot)) {
                $wwwrootparts = parse_url($CFG->wwwroot);
                $wwwrootpath = $wwwrootparts['path'] ?? '';
                if (!empty($wwwrootpath) && strpos($urlstring, $wwwrootpath) === 0) {
                    $urlstring = substr($urlstring, strlen($wwwrootpath));
                }
                if (strpos($urlstring, $CFG->wwwroot) === 0) {
                    $urlstring = substr($urlstring, strlen($CFG->wwwroot));
                }
            }

            if (!empty($urlstring) && $urlstring[0] !== '/') {
                $urlstring = '/' . $urlstring;
            }

            if (empty($urlstring) || $urlstring === '/') {
                $urlstring = '/';
            }

            if (strpos($urlstring, '?') !== false) {
                $urlparts = parse_url($urlstring);
                $path = $urlparts['path'] ?? $urlstring;
                $urlqueryparams = [];
                if (isset($urlparts['query'])) {
                    parse_str($urlparts['query'], $urlqueryparams);
                }
                return new \moodle_url($path, $urlqueryparams);
            }

            return new \moodle_url($urlstring);
        } catch (\Exception $e) {
            return null;
        }
    }
}
