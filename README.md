# Smart Search for Moodle

A unified, intelligent search plugin for Moodle that helps administrators, instructors, and learners **find courses, activities, users, and admin pages in seconds**.

Smart Search complements Moodle's built-in Global Search by focusing on **fast navigation** rather than full-text content excerpts. Users get instant suggestions while typing, a dedicated results page for deeper exploration, and quick actions directly from search results.

**Product page:** [Smart Search – MooPlugins](https://www.mooplugins.com/smart-search-moodle-plugin/)  
**Documentation:** [Smart Search documentation](https://www.mooplugins.com/smart-search-moodle-plugin/) (see Learn More on the product page)

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

## Development

### Code quality

Run Moodle's development plugins locally:

- [local_codechecker](https://moodle.org/plugins/local_codechecker)
- [local_moodlecheck](https://moodle.org/plugins/local_moodlecheck)

### Local CI (mirrors GitHub Actions)

Automated checks catch **coding standards, structure, templates, JS lint, architecture regressions, and N+1 query patterns** via the **quality-gate** step and PHPUnit (`query_performance_test`). Use the [pre-submit checklist](#pre-submit-checklist-before-pr-or-moodleorg-release) for manual browser smoke tests.

#### From the Moodle project root

Local CI uses [moodle-plugin-ci](https://github.com/moodlehq/moodle-plugin-ci) via `tools/ci-local.sh`. The plugin is registered in `tools/ci-plugins.conf`.

**One-time setup** (from the Moodle root, e.g. `moodle52plus/`):

```bash
chmod +x tools/ci-setup.sh tools/ci-local.sh
./tools/ci-setup.sh
```

Prerequisites: **Node.js 20+** for Grunt (if you use `nvm`, `tools/ci-grunt.sh` will try `nvm use 22` automatically). The **phpmd** step prints complexity hints only — it does not fail CI.

**Run full CI before every PR** (same steps as GitHub Actions — includes Mustache and Grunt):

```bash
export PHPRC=/path/to/moodle/.php.ini   # required if PHP max_input_vars is 1000
export MOODLE_ROOT=/path/to/moodle
export MOODLE_DIR=/path/to/moodle

./tools/ci-local.sh --plugin local_smartsearch --strict
```

Use `--skip-grunt` only for a quick PHP-only pass while iterating; **do not rely on it before merge**.

#### From the standalone plugin repository

If this folder is its own Git repo (plugin root = repo root):

```bash
chmod +x tools/ci-check.sh
export PATH="$HOME/moodle-plugin-ci/bin:$PATH"   # after composer create-project moodlehq/moodle-plugin-ci

./tools/ci-check.sh --strict
```

Rebuild AMD after JS changes (from Moodle root):

```bash
cd /path/to/moodle
npx grunt amd --root=public/local/smartsearch
```

Useful options (`ci-local.sh` / `ci-check.sh`):

| Option | Purpose |
|--------|---------|
| `--strict` | Stop on first failure (recommended before PR) |
| `--phpunit-only` | Run PHPUnit only (Moodle root script) |
| `--skip-grunt` | Skip Grunt — faster, **not** pre-merge parity with GitHub |
| `--install` | Run `moodle-plugin-ci install` (clones Moodle like GitHub Actions) |

**What automated CI enforces**

| Step | Fails build? | Notes |
|------|----------------|-------|
| phplint | yes | |
| phpmd | no (informational) | Warnings only |
| codechecker | yes | `--max-warnings 0` |
| phpdoc | yes | |
| validate | yes | Plugin structure, `version.php`, etc. |
| savepoints | yes | |
| mustache | yes | Template syntax and examples |
| **quality-gate** | yes | jQuery ban, Mustache-for-HTML, LICENSE/CSS, N+1 regression patterns |
| grunt | yes | ESLint on AMD; run unless explicitly skipping locally |
| PHPUnit | yes | Includes **query performance** test (`query_performance_test`) |

**PHPUnit only**

```bash
export PHPRC=/path/to/moodle/.php.ini
php admin/tool/phpunit/cli/init.php   # once, or after plugin version bumps

vendor/bin/phpunit --configuration phpunit.xml --testsuite local_smartsearch_testsuite
```

### Pre-submit checklist (before PR or moodle.org release)

Complete this **after** `./tools/ci-local.sh --strict` or `./tools/ci-check.sh --strict` passes.

**Automated (must pass)**

- [ ] `./tools/ci-local.sh --strict` or `./tools/ci-check.sh --strict` (full moodle-plugin-ci + **quality-gate**)
- [ ] `codechecker --max-warnings 0` — no errors or warnings
- [ ] `moodlecheck` / `phpdoc` — no errors on changed PHP files
- [ ] `mustache` — all templates valid (including `@template` + example JSON)
- [ ] `grunt amd` — ESLint clean; commit updated `amd/build/*`
- [ ] PHPUnit — all tests pass (including **search read-count budget**); re-run `php admin/tool/phpunit/cli/init.php` after `version.php` bumps
- [ ] `CHANGES.md` updated; `$plugin->version` and `$plugin->release` bumped

**Manual review (browser smoke test)**

- [ ] Test on at least one target Moodle version (4.5 / 5.0 / 5.2): search overlay, results page, settings index, analytics report
- [ ] Optional: enable **Debugging → Performance info** and confirm no obvious query spikes on search (quality-gate + PHPUnit already guard regressions)

**Release to moodle.org** (optional, after merge)

- [ ] Plugin registered on [moodle.org/plugins](https://moodle.org/plugins/)
- [ ] GitHub secret `MOODLE_ORG_TOKEN` set (see [moodle-plugin-release](https://github.com/moodlehq/moodle-plugin-release))
- [ ] Tag matches release, e.g. `git tag v1.0.5 && git push origin v1.0.5`

### CI on GitHub

This plugin is designed as a **standalone Git repository** (plugin root = repo root). GitHub Actions runs [moodle-plugin-ci](https://github.com/moodlehq/moodle-plugin-ci) on push/PR against Moodle 4.5, 5.0, and 5.2 (see `.github/workflows/moodle-ci.yml`).

#### Create the GitHub repository

1. Create an empty repo on GitHub (recommended name: `moodle-local_smartsearch`).
2. From this directory (`local/smartsearch`):

```bash
git init
git add .
git commit -m "Initial commit: Smart Search for Moodle"
git branch -M main
git remote add origin git@github.com:YOUR_ORG/moodle-local_smartsearch.git
git push -u origin main
```

3. Open the repo on GitHub → **Actions** — the Moodle Plugin CI workflow should run automatically.

#### Install from the standalone repo

Clone into your Moodle tree:

```bash
cd /path/to/moodle
git clone git@github.com:YOUR_ORG/moodle-local_smartsearch.git local/smartsearch
```

Then visit **Site administration → Notifications** to install.

#### CI without a full Moodle checkout (plugin only)

GitHub Actions checks out **only this plugin** into `$GITHUB_WORKSPACE/plugin`, then `moodle-plugin-ci install` clones Moodle and installs the plugin — the same flow as [MooPlugins reference workflows](https://github.com/mooplugins/moodle-mod_videolesson/tree/main/.github/workflows).

#### Performance notes for reviewers

See [PERFORMANCE.md](PERFORMANCE.md) for N+1 mitigations and remaining per-result permission work.

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
