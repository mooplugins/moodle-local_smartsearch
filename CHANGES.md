# Changelog

All notable changes to the Smart Search plugin are documented here.

## 1.0.14 - 2026-08-19

### Added

- Redesigned search overlay and dedicated results page with a cleaner, sectioned layout.
- In-search feedback modal so users can submit suggestions and bug reports from the search UI.
- SVG icons for result categories (course, activity, user, setting, plugin, category).
- Expanded role-aware permission filtering for search results (courses, activities, users, settings, plugins, categories, and admin URLs).
- Search analytics report page with a top-queries chart for administrators.

### Improved

- Search result subtitles and metadata are trimmed per viewer role (for example, user profile metadata and course enrolment counts).
- Feedback form select control now shows a visible dropdown indicator.
- Per-request permission caching during search for better performance on large result sets.

### Fixed

- Students no longer see "Enrolled users" counts on course search results.
- Subtitle permission stripping now works with both legacy pipe (`|`) and bullet (`•`) separators in indexed data.
- Analytics report no longer crashes on Moodle 4.5 when responsive chart options are unavailable (`set_responsive_options()` guarded for older core chart APIs).
- Moodle Plugin CI stylelint compliance for plugin CSS (no quoted or `data:` URI values in `url()`).

## 1.0.9 - 2026-06-24

### Added

- Initial public release of Smart Search for Moodle.
- Command-palette-style search interface.
- Search support for courses, activities, users, settings, plugins, and categories.
- Dedicated results page with category filters.
- Quick actions based on Moodle capabilities.
- Optional anonymous search analytics.
- Moodle Privacy API support.
- GitHub Actions Moodle Plugin CI workflow.
- PHPUnit tests for privacy metadata and analytics cleanup.
- `thirdpartylibs.xml`, `LICENSE`, and contributor documentation.

### Fixed

- Coding standards and PHPDoc compliance.
- External webservice return type.
- Stale PHPDoc on `indexer::add_to_index()`.
