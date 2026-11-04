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
 * English strings for local_advancedexport.
 *
 * Developed by Rui Pratas for the Training Centre of the Portuguese Ministry of Foreign Affairs
 * (Centro de Formação do Ministério dos Negócios Estrangeiros).
 *
 * @package    local_advancedexport
 * @copyright  2026 Rui Pratas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['advancedexport:export'] = 'Use advanced export of responses';
$string['anonymous'] = 'Anonymous';
$string['anonymouswarning'] = 'This questionnaire is anonymous. To protect respondents, only the Summary sheet (totals per option) can be exported, without names.';
$string['col_count'] = 'Number of responses';
$string['col_option'] = 'Option';
$string['col_percent'] = '% of responses';
$string['col_pickedin'] = 'Chosen in {$a}';
$string['col_question'] = 'Question';
$string['col_responses'] = 'Submitted responses';
$string['col_status'] = 'Status';
$string['col_submitted'] = 'Submission date';
$string['col_type'] = 'Type';
$string['columns_questions'] = 'Answers to other questions';
$string['columns_user'] = 'Moodle profile';
$string['credits'] = 'Developed by Rui Pratas for the Training Centre of the Portuguese Ministry of Foreign Affairs';
$string['error_nogroup'] = 'Choose at least one question.';
$string['error_nosheet'] = 'Choose at least one sheet.';
$string['export'] = 'Export';
$string['exporttitle'] = 'Advanced export: {$a}';
$string['format'] = 'File format';
$string['format_csv'] = 'CSV (plain data; several sheets come in a ZIP)';
$string['format_ods'] = 'LibreOffice (.ods)';
$string['format_pdf'] = 'PDF (to print or send)';
$string['format_xlsx'] = 'Excel (.xlsx)';
$string['identified'] = 'With names';
$string['includeincomplete'] = 'Unsubmitted responses';
$string['includeincomplete_desc'] = 'Include saved but not submitted responses ({$a} at the moment). A "Status" column is added.';
$string['indexintro'] = 'Every Questionnaire on the site. Choose one to export its responses organised by respondent and by option.';
$string['intro'] = 'Submitted responses: <strong>{$a->complete}</strong>. Saved but not submitted: {$a->incomplete}.';
$string['menuitem'] = 'Advanced export';
$string['nogroupable'] = 'This questionnaire has no option questions (check boxes, radio buttons, dropdown or yes/no).';
$string['noquestionnaires'] = 'No questionnaires found.';
$string['note_anonymous'] = 'Anonymous questionnaire: respondents are not identified.';
$string['note_count'] = 'People: {$a}';
$string['note_multiple'] = 'A person may choose more than one option, so totals can exceed the number of responses.';
$string['note_total'] = 'Total responses considered: {$a}';
$string['pluginname'] = 'Advanced export of responses';
$string['privacy:metadata:preference:lastchoices'] = 'The export options last used in each activity, so the form opens with them filled in.';
$string['questionprefix'] = 'Q{$a}';
$string['searchplaceholder'] = 'Search by course or questionnaire';
$string['section_columns'] = 'Respondent information';
$string['section_columns_help'] = 'Columns shown next to each person, in this order.';
$string['section_format'] = 'Format';
$string['section_group'] = 'Questions to organise by';
$string['section_group_help'] = 'The options of these questions become the groups of the export (for example, the languages or courses chosen). With more than one question, a ✓ column shows where each option was chosen.';
$string['section_sheets'] = 'Sheets';
$string['selectallnone'] = 'Select all / none';
$string['sheet_byoption'] = 'By option';
$string['sheet_byoption_desc'] = '<strong>By option</strong>: the option cell is merged vertically, one row per person';
$string['sheet_byuser'] = 'By respondent';
$string['sheet_byuser_desc'] = '<strong>By respondent</strong>: one row per person with the options chosen';
$string['sheet_default'] = 'Sheet';
$string['sheet_peroption_desc'] = '<strong>One sheet per option</strong>: a separate list for each option (handy for class lists)';
$string['sheet_summary'] = 'Summary';
$string['sheet_summary_desc'] = '<strong>Summary</strong>: number of people per option';
$string['status_complete'] = 'Submitted';
$string['status_incomplete'] = 'Not submitted';
