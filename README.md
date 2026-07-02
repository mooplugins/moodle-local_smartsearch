# Smart Search for Moodle

A unified, intelligent search plugin for Moodle that helps administrators, instructors, and learners **find courses, activities, users, and admin pages in seconds**.

Smart Search complements Moodle's built-in Global Search by focusing on **fast navigation** rather than full-text content excerpts. Users get instant suggestions while typing, a dedicated results page for deeper exploration, and quick actions directly from search results.

**Product page:** [Smart Search – MooPlugins](https://www.mooplugins.com/blog/smart-search-moodle-plugin/)  
**Documentation:** [Smart Search documentation](https://www.mooplugins.com/docs/) (see Learn More on the product page)

## Why Smart Search?

Moodle's **Global Search** indexes broad content areas (courses, activities, forum posts, settings, and more) and can return **content excerpts** from indexed text. That is useful for discovery, but day-to-day work often needs something different: a quick way to **jump directly to a course, activity, user, or admin page** without clicking through many screens.

Smart Search is built for that workflow. It provides a modern, command-palette-style experience (Ctrl+K / Cmd+K) with structured, context-rich results and optional analytics for administrators.

## Features

### Instant results while you type

Search begins as soon as a user types in the search bar. Results appear in a dropdown overlay grouped by category (courses, activities, users, categories, and more). Users can open an item immediately or press Enter to view the full results page.

### Dedicated results page

When more matches are needed, Smart Search opens a **results page** with the complete list across all searchable areas. Users can filter results by category (courses, activities, users, settings, plugins, categories).

### Quick actions

Relevant actions (view, edit, send message, login as, and others) are available directly from search results, depending on the user's permissions.

### Flexible configuration

Administrators control which content types are searchable:

- Users (with optional email search when permitted)
- Courses
- Activities and resources
- Site settings
- Course categories
- Plugin and admin pages

Categories can be enabled or disabled to match how the site is used.

### Optional analytics

When enabled, Smart Search records **anonymous search queries** and provides an analytics report with insights such as:

- Most searched keywords
- Top search queries
- Search activity over time

Analytics is enabled by default with a **365-day retention** period; both can be changed in plugin settings. Search logs do not store personally identifiable user information tied to queries.

### Built for Moodle

- **Capability-based access** — respects Moodle capabilities and enrolments
- **Real-time indexing** — index updates when content changes
- **Privacy API** — metadata, export, and deletion support for indexed data
- **Keyboard-driven UI** — Ctrl+K / Cmd+K from anywhere in the site

Read more: https://www.mooplugins.com/blog/smart-search-moodle-plugin/

## Requirements

- Moodle 4.5 or higher (CI tested on 4.5, 5.0, 5.2)
- MySQL/MariaDB with FULLTEXT index support

## Installation

1. Copy the `smartsearch` folder to `local/smartsearch` in your Moodle codebase.
2. Visit **Site administration → Notifications** to install the database tables.
3. Configure at **Site administration → Plugins → Local plugins → Smart Search**.
4. Run **Index now** on the settings page (or wait for the scheduled indexing task).

When Smart Search is enabled, Global Search is disabled automatically so users have a single, consistent search experience.

## Configuration

Key settings are available under **Site administration → Plugins → Local plugins → Smart Search**:

| Setting | Description |
|--------|-------------|
| Enable Smart Search | Turn the plugin on or off site-wide |
| Search categories | Enable/disable users, courses, activities, settings, plugins, categories |
| Search user emails | Allow email addresses in user search (permission-dependent) |
| Enable analytics | Log anonymous search queries |
| Analytics retention | How long to keep log data (default: 365 days) |
| Index now | Trigger a full re-index manually |

The analytics report is available at **Site administration → Reports → Smart Search Analytics** when analytics is enabled.

## Privacy

The plugin implements the Moodle Privacy API (`classes/privacy/provider.php`).

- **Search index** (`local_smartsearch_index`) — copies of searchable titles, subtitles, and metadata used to power search.
- **Analytics log** (`local_smartsearch_log`) — anonymous query text, result counts, and timestamps when analytics is enabled.

See the plugin's privacy provider for export and deletion support.

## Changelog

See [CHANGES.md](CHANGES.md).

## License

GPL v3 or later. See [LICENSE](LICENSE).

## Credits

Developed by [MooPlugins](https://www.mooplugins.com/).
