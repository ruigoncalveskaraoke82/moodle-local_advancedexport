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
 * Central page: every Questionnaire on the site, with a link to export it.
 *
 * Developed by Rui Pratas for the Training Centre of the Portuguese Ministry of Foreign Affairs
 * (Centro de Formação do Ministério dos Negócios Estrangeiros).
 *
 * @package    local_advancedexport
 * @copyright  2026 Rui Pratas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_advancedexport');

$search = trim(optional_param('search', '', PARAM_TEXT));
$PAGE->set_url(new moodle_url('/local/advancedexport/index.php', $search !== '' ? ['search' => $search] : []));

$params = ['modname' => 'questionnaire', 'y' => 'y'];
$where = '';
if ($search !== '') {
    $where = 'AND (' . $DB->sql_like('c.fullname', ':s1', false) . ' OR ' . $DB->sql_like('c.shortname', ':s2', false)
        . ' OR ' . $DB->sql_like('q.name', ':s3', false) . ')';
    $params['s1'] = $params['s2'] = $params['s3'] = '%' . $DB->sql_like_escape($search) . '%';
}
$sql = "SELECT cm.id AS cmid, q.name, q.respondenttype, c.id AS courseid, c.fullname AS coursename,
               (SELECT COUNT(1) FROM {questionnaire_response} r WHERE r.questionnaireid = q.id AND r.complete = :y) AS responses
          FROM {course_modules} cm
          JOIN {modules} m ON m.id = cm.module AND m.name = :modname
          JOIN {questionnaire} q ON q.id = cm.instance
          JOIN {course} c ON c.id = cm.course
         WHERE cm.deletioninprogress = 0 $where
      ORDER BY c.fullname ASC, q.name ASC";
$records = $DB->get_records_sql($sql, $params);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_advancedexport'));
echo html_writer::div(get_string('indexintro', 'local_advancedexport'), 'mb-3');

echo html_writer::start_tag('form', ['method' => 'get', 'action' => $PAGE->url->out_omit_querystring(),
    'class' => 'form-inline mb-3 d-flex gap-2']);
echo html_writer::empty_tag('input', ['type' => 'text', 'name' => 'search', 'value' => $search,
    'class' => 'form-control', 'placeholder' => get_string('searchplaceholder', 'local_advancedexport'), 'size' => 40]);
echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('search'), 'class' => 'btn btn-secondary']);
echo html_writer::end_tag('form');

$table = new html_table();
$table->head = [
    get_string('course'),
    get_string('modulename', 'questionnaire'),
    get_string('col_responses', 'local_advancedexport'),
    get_string('col_type', 'local_advancedexport'),
    '',
];
$table->attributes['class'] = 'generaltable table-sm';
foreach ($records as $r) {
    $context = context_module::instance($r->cmid, IGNORE_MISSING);
    if (!$context || !has_capability('local/advancedexport:export', $context)) {
        continue;
    }
    $table->data[] = [
        html_writer::link(new moodle_url('/course/view.php', ['id' => $r->courseid]), format_string($r->coursename)),
        html_writer::link(new moodle_url('/mod/questionnaire/view.php', ['id' => $r->cmid]), format_string($r->name)),
        $r->responses,
        $r->respondenttype === 'anonymous' ? get_string('anonymous', 'local_advancedexport')
            : get_string('identified', 'local_advancedexport'),
        html_writer::link(
            new moodle_url('/local/advancedexport/export.php', ['id' => $r->cmid]),
            get_string('export', 'local_advancedexport'),
            ['class' => 'btn btn-primary btn-sm']
        ),
    ];
}
if (empty($table->data)) {
    echo $OUTPUT->notification(get_string('noquestionnaires', 'local_advancedexport'), 'info');
} else {
    echo html_writer::table($table);
}
echo html_writer::div(get_string('credits', 'local_advancedexport'), 'text-muted small mt-4');
echo $OUTPUT->footer();
