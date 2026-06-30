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
 * Static architecture and regression checks for Smart Search CI.
 *
 * @package    local_smartsearch
 * @copyright  2025 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_smartsearch;

/**
 * Static architecture and N+1 regression checks for CI.
 */
final class quality_gate {
    /** @var string */
    private string $pluginroot;

    /** @var string[] */
    private array $errors = [];

    /**
     * Constructor.
     *
     * @param string $pluginroot Absolute path to the plugin root directory.
     */
    public function __construct(string $pluginroot) {
        $this->pluginroot = $pluginroot;
    }

    /**
     * Run all checks.
     *
     * @return int Exit code (0 = pass, 1 = fail).
     */
    public function run(): int {
        $this->check_javascript_architecture();
        $this->check_php_regressions();
        $this->check_license_and_css();
        $this->check_mustache_templates();

        if ($this->errors !== []) {
            fwrite(STDERR, "Quality gate failed:\n");
            foreach ($this->errors as $error) {
                fwrite(STDERR, "  - {$error}\n");
            }
            return 1;
        }

        echo "Quality gate passed.\n";
        return 0;
    }

    /**
     * Record a quality gate failure.
     *
     * @param string $message
     */
    private function fail(string $message): void {
        $this->errors[] = $message;
    }

    /**
     * Read a file relative to plugin root.
     *
     * @param string $relative
     * @return string
     */
    private function read(string $relative): string {
        $path = $this->pluginroot . '/' . $relative;
        if (!is_readable($path)) {
            $this->fail("Missing file: {$relative}");
            return '';
        }
        return (string) file_get_contents($path);
    }

    /**
     * JavaScript architecture checks.
     */
    private function check_javascript_architecture(): void {
        $jsfiles = glob($this->pluginroot . '/amd/src/*.js') ?: [];
        foreach ($jsfiles as $jsfile) {
            $relative = 'amd/src/' . basename($jsfile);
            $content = file_get_contents($jsfile);
            $lines = preg_split('/\R/', $content) ?: [];

            if (preg_match("/define\s*\(\s*\[[^\]]*['\"]jquery['\"]/i", $content)) {
                $this->fail("{$relative}: jQuery dependency is not allowed in AMD modules.");
            }
            if (preg_match('/\$\.(ajax|fn|)\(/', $content)) {
                $this->fail("{$relative}: jQuery API usage is not allowed.");
            }

            foreach ($lines as $lineno => $line) {
                $linenum = $lineno + 1;
                if (preg_match('/\bhtml\s*\+=\s*[\'"]</', $line)) {
                    $this->fail("{$relative}:{$linenum}: build HTML with Mustache, not string concatenation.");
                }
                if (preg_match('/\.html\s*\(\s*[\'"]</', $line)) {
                    $this->fail("{$relative}:{$linenum}: build HTML with Mustache, not .html() string literals.");
                }
                if (preg_match('/innerHTML\s*=\s*[\'"]</', $line)) {
                    $this->fail("{$relative}:{$linenum}: use Mustache templates instead of innerHTML string literals.");
                }
            }
        }
    }

    /**
     * PHP N+1 and architecture regression checks.
     */
    private function check_php_regressions(): void {
        $queryphp = $this->read('classes/query.php');
        if ($queryphp !== '' && preg_match('/foreach\s*\([^)]+\)\s*\{[^}]*get_item_url\s*\(/s', $queryphp)) {
            $this->fail('classes/query.php: use resolve_result_url() / indexed URLs inside result loops.');
        }
        if ($queryphp !== '' && !str_contains($queryphp, 'permissions::begin_search_context()')) {
            $this->fail('classes/query.php: search must reset permission caches via begin_search_context().');
        }

        $indexerphp = $this->read('classes/indexer.php');
        if ($indexerphp !== '' && !preg_match('/function\s+cleanup_orphaned_entries.*?record_exists\s*\(/s', $indexerphp)) {
            $this->fail('classes/indexer.php: orphan cleanup must use record_exists(), not index_item().');
        }
        $addtoindexpattern = '/function\s+add_to_index\s*\([^)]*\)[^{]*\{[^}]*get_record\s*\(\s*[\'"]local_smartsearch_index/s';
        if ($indexerphp !== '' && preg_match($addtoindexpattern, $indexerphp)) {
            $this->fail('classes/indexer.php: add_to_index() must use the existing-index map, not get_record() per item.');
        }

        $pluginphp = $this->read('classes/search_area/plugin.php');
        if ($pluginphp !== '' && preg_match('/new\s+\\\\moodle_url\s*\(\s*[\'"]\/admin\/category\.php[\'"]\s*\)/', $pluginphp)) {
            $this->fail('classes/search_area/plugin.php: must not hardcode /admin/category.php without category param.');
        }

        $permissionsphp = $this->read('classes/permissions.php');
        if ($permissionsphp !== '' && !str_contains($permissionsphp, 'begin_search_context')) {
            $this->fail('classes/permissions.php: missing per-request search context caches.');
        }
    }

    /**
     * LICENSE and CSS boilerplate checks.
     */
    private function check_license_and_css(): void {
        $license = $this->read('LICENSE');
        if ($license !== '' && stripos($license, 'GNU GENERAL PUBLIC LICENSE') === false) {
            $this->fail('LICENSE: must contain the full GNU General Public License text.');
        }
        if ($license !== '' && strlen($license) < 5000) {
            $this->fail('LICENSE: file looks too short; use https://www.gnu.org/licenses/gpl-3.0.txt');
        }

        foreach (['styles.css', 'report/css/style.css'] as $cssrel) {
            $css = $this->read($cssrel);
            if ($css !== '' && !preg_match('/@package\s+local_smartsearch/', $css)) {
                $this->fail("{$cssrel}: missing @package local_smartsearch boilerplate.");
            }
        }
    }

    /**
     * Mustache templates must declare @template.
     */
    private function check_mustache_templates(): void {
        $mustachefiles = glob($this->pluginroot . '/templates/*.mustache') ?: [];
        foreach ($mustachefiles as $mustachefile) {
            $relative = 'templates/' . basename($mustachefile);
            $content = file_get_contents($mustachefile);
            if (!preg_match('/@template\s+local_smartsearch\//', $content)) {
                $this->fail("{$relative}: missing @template local_smartsearch/... docblock.");
            }
        }
    }
}
