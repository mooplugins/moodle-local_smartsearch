# Smart Search — performance notes

This document summarises how Smart Search avoids common N+1 query patterns and what reviewers should expect during load.

## Search requests (`classes/query.php`)

Each search runs one SQL query **per enabled area** (not per result). Permission filtering happens in PHP after the SQL returns.

### Optimisations

| Area | Approach |
|------|----------|
| Result URLs | Prefer the URL stored in `local_smartsearch_index` instead of calling `get_item_url()` again for course, activity, and category hits. User URLs still use `permissions::get_user_result_url()` (context-aware). |
| Activity permissions | `permissions::get_cm_info_for_user()` caches `get_fast_modinfo()` and `cm_info` per `(userid, cmid)` for the request. |
| Course visibility | `can_view_course()` caches `(userid, courseid)` and reuses `has_course_membership()` plus a minimal cached course row. |
| User visibility | `can_view_user()` caches `(viewer, target)` so `can_index()` and `get_user_result_url()` do not repeat shared-course SQL. |
| Shared-course lookup | `get_shared_visible_course_for_users()` caches `(viewerid, targetuserid) → courseid` and streams candidates with a recordset (stops at first match). |
| Course membership | `has_course_membership()` caches `(userid, courseid)`. |
| Category catalog URLs | `has_accessible_course_in_category()` uses one `EXISTS` SQL instead of looping up to 200 courses with separate membership queries. |
| Activity actions | `actions::get_activity_actions()` reuses cached `cm_info` instead of loading the CM again. |
| Quick actions | `get_item_actions()` runs only after URL access checks pass, skipping action work for filtered hits. |

### Request lifecycle

`permissions::begin_search_context()` clears per-request caches at the start of each `query::search()` call.

### Remaining per-result work (by design)

- `can_index()` — capability checks per hit (required for security).
- `get_item_actions()` — quick actions depend on the viewer's capabilities (deferred until after URL access).
- `can_access_search_result_url()` — filters admin-only indexed URLs.

These are cached where possible but still run per visible result.

## Indexing (`classes/indexer.php`)

| Area | Approach |
|------|----------|
| Index upserts | Existing index rows for an area are loaded once into a map; `add_to_index()` no longer calls `get_record()` per item. |
| Activity indexing | Recordset rows are passed to `activity::index_item()`; `get_modinfo_for_course()` caches one `get_fast_modinfo()` per course during bulk indexing. |
| User indexing | `user::begin_bulk_index()` preloads all role assignments and enrolment counts in two site-wide queries before the user recordset loop. |
| Category paths | `category_path::get_path()` loads all categories once per `index_all()` run. |
| Orphan cleanup | `record_exists()` on each search area (cheap `record_exists` on source tables) instead of full `index_item()` re-index. |
| Course indexing | Removed unused site-wide `count_records('user_enrolments')` that ran on every course. |

### Settings and plugin pages

These use hash-based record IDs. Orphan cleanup skips them; entries are refreshed on full reindex via `index_all_settings()` / plugin tree walk.

## Frontend

- Search results are rendered with Mustache templates (`result_item`, `result_category`) instead of string-built HTML in JavaScript.
- AMD modules use native DOM APIs and `fetch` (no jQuery dependency).

## CI performance guard

`tests/query_performance_test.php` asserts bounded DB read counts for:

- 20 course hits (admin search)
- 20 activity hits in one course (modinfo reuse)

Run locally:

```bash
export PHPRC=/path/to/moodle/.php.ini
vendor/bin/phpunit --configuration phpunit.xml --filter query_performance_test
```

## Moodle versions tested in CI

- Moodle 4.5 (PHP 8.1, PostgreSQL)
- Moodle 5.0 (PHP 8.2, PostgreSQL)
- Moodle 5.2 (PHP 8.3, MariaDB)

See `.github/workflows/moodle-ci.yml` for the full matrix.
