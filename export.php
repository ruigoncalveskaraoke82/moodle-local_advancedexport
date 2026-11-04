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
 * Export page for one Questionnaire activity.
 *
 * Developed by Rui Pratas for the Training Centre of the Portuguese Ministry of Foreign Affairs
 * (Centro de Formação do Ministério dos Negócios Estrangeiros).
 *
 * @package    local_advancedexport
 * @copyright  2026 Rui Pratas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_advancedexport\form\export_form;
use local_advancedexport\local\builder;
use local_advancedexport\local\questionnaire_source;
use local_advancedexport\local\writer;

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('questionnaire', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('local/advancedexport:export', $context);
$questionnaire = $DB->get_record('questionnaire', ['id' => $cm->instance], '*', MUST_EXIST);

$url = new moodle_url('/local/advancedexport/export.php', ['id' => $cm->id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('exporttitle', 'local_advancedexport', format_string($cm->name)));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->activityheader->disable();

$source = new questionnaire_source($cm, $questionnaire);
$groupable = $source->get_groupable_questions();
$counts = $source->count_responses();
$prefname = 'local_advancedexport_lastchoices_' . $cm->id;

$form = new export_form($url, [
    'groupable' => $groupable,
    'questions' => $source->get_questions(),
    'userfields' => questionnaire_source::get_user_fields(),
    'anonymous' => $source->is_anonymous(),
    'counts' => $counts,
]);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/mod/questionnaire/view.php', ['id' => $cm->id]));
}

if ($data = $form->get_data()) {
    $choices = $form->choices_from_data($data);
    set_user_preference($prefname, json_encode($choices));

    $source->load_responses($choices['includeincomplete']);
    $builder = new builder($source, $choices['groups'], $choices['columns']);
    $sheets = $builder->build($choices['sheets']);

    $name = $source->get_name();
    $basename = $name . ' - ' . userdate(time(), '%Y-%m-%d');
    [$path, $filename] = writer::write($sheets, $choices['format'], $basename, $name);

    \core\session\manager::write_close();
    send_temp_file($path, $filename);
}

// Defaults: the user's last choices for this activity, otherwise sensible ones.
$saved = json_decode((string)get_user_preferences($prefname, ''), true);
if (is_array($saved)) {
    $defaults = export_form::defaults_from_choices($saved);
} else {
    $defaults = ['format' => 'xlsx', 'u_fullname' => 1, 'u_email' => 1, 'c_submitted' => 1,
        's_summary' => 1, 's_byuser' => 1, 's_byoption' => 1, 's_peroption' => 0];
    foreach ($groupable as $qid => $q) {
        if ((int)$q->type_id === questionnaire_source::TYPE_CHECKBOX) {
            $defaults['g_' . $qid] = 1;
        }
    }
}
if ($source->is_anonymous()) {
    $defaults = array_merge($defaults, ['s_summary' => 1, 's_byuser' => 0, 's_byoption' => 0, 's_peroption' => 0]);
}
$defaults['id'] = $cm->id;
$form->set_data($defaults);

$PAGE->requires->js_call_amd(
    'local_advancedexport/columns_toggle',
    'init',
    ['local-advancedexport-toggle-q', 'checkboxgroup3']
);
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('menuitem', 'local_advancedexport'), 3);
[$complete, $incomplete] = $counts;
echo html_writer::div(get_string('intro', 'local_advancedexport', (object)[
    'complete' => $complete,
    'incomplete' => $incomplete,
]), 'mb-3');
$form->display();
echo html_writer::div(get_string('credits', 'local_advancedexport'), 'text-muted small mt-4');
echo $OUTPUT->footer();
