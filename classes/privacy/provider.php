<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_advancedexport\privacy;

use core_privacy\local\metadata\collection;

/**
 * Privacy provider. The plugin only reads data; it stores the exporter's last choices as a user preference.
 *
 * Developed by Rui Pratas for the Training Centre of the Portuguese Ministry of Foreign Affairs
 * (Centro de Formação do Ministério dos Negócios Estrangeiros).
 *
 * @package    local_advancedexport
 * @copyright  2026 Rui Pratas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\user_preference_provider {
    /**
     * Describe stored data.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_user_preference('local_advancedexport_lastchoices', 'privacy:metadata:preference:lastchoices');
        return $collection;
    }

    /**
     * Export the user's preferences.
     *
     * @param int $userid
     */
    public static function export_user_preferences(int $userid) {
        $prefs = get_user_preferences(null, null, $userid);
        foreach ($prefs as $name => $value) {
            if (strpos($name, 'local_advancedexport_') === 0) {
                \core_privacy\local\request\writer::export_user_preference(
                    'local_advancedexport',
                    $name,
                    $value,
                    get_string('privacy:metadata:preference:lastchoices', 'local_advancedexport')
                );
            }
        }
    }
}
