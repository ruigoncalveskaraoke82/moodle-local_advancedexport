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

/**
 * Navigation hooks for local_advancedexport.
 *
 * Developed by Rui Pratas for the Training Centre of the Portuguese Ministry of Foreign Affairs
 * (Centro de Formação do Ministério dos Negócios Estrangeiros).
 *
 * @package    local_advancedexport
 * @copyright  2026 Rui Pratas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Adds "Advanced export" to the menu of every Questionnaire activity.
 *
 * @param settings_navigation $nav
 * @param context $context
 */
function local_advancedexport_extend_settings_navigation(settings_navigation $nav, context $context) {
    if ($context->contextlevel != CONTEXT_MODULE) {
        return;
    }
    $cm = $nav->get_page()->cm;
    if (!$cm || $cm->modname !== 'questionnaire') {
        return;
    }
    if (!has_capability('local/advancedexport:export', $context)) {
        return;
    }
    $node = $nav->find('modulesettings', navigation_node::TYPE_SETTING);
    if (!$node) {
        return;
    }
    $node->add(
        get_string('menuitem', 'local_advancedexport'),
        new moodle_url('/local/advancedexport/export.php', ['id' => $cm->id]),
        navigation_node::TYPE_SETTING,
        null,
        'local_advancedexport',
        new pix_icon('i/export', '')
    );
}
