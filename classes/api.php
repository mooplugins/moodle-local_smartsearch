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
 * API class for Smart Search plugin registration.
 *
 * @package    local_smartsearch
 * @copyright  2025 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_smartsearch;


/**
 * API for plugin registration and extensibility.
 */
class api {
    /** @var array Registered search areas */
    protected static $registeredareas = [];

    /**
     * Get all registered search areas.
     *
     * @return array Array of search_area\base instances
     */
    public static function get_registered_areas(): array {
        $areas = [];

        foreach (self::$registeredareas as $registration) {
            // Check if plugin is still installed and enabled.
            if (!\core_plugin_manager::instance()->get_plugin_info($registration['component'])) {
                continue;
            }

            try {
                $area = new $registration['class']();
                $areas[] = $area;
            } catch (\Exception $e) {
                debugging(
                    "Error instantiating search area {$registration['component']}/{$registration['areaname']}: "
                    . $e->getMessage()
                );
            }
        }

        return $areas;
    }
}
