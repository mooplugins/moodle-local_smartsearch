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
 * URL validation tests for Smart Search results.
 *
 * @package    local_smartsearch
 * @copyright  2025 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_smartsearch;

/**
 * Ensures incomplete admin URLs are rejected for results and actions.
 *
 * @covers \local_smartsearch\permissions
 */
final class url_test extends \advanced_testcase {
    /**
     * Incomplete admin URLs must not be linkable.
     */
    public function test_is_usable_result_url_rejects_incomplete_admin_links(): void {
        $this->assertFalse(permissions::is_usable_result_url('/admin/category.php'));
        $this->assertFalse(permissions::is_usable_result_url('/admin/settings.php'));
        $this->assertTrue(permissions::is_usable_result_url('/admin/category.php?category=users'));
        $this->assertTrue(permissions::is_usable_result_url('/admin/settings.php?section=users'));
        $this->assertTrue(permissions::is_usable_result_url('/admin/user.php'));
    }
}
