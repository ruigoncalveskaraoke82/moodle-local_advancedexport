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

namespace local_advancedexport\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Export options form.
 *
 * Custom data: groupable (qid => question), questions (qid => question), userfields (key => label),
 * anonymous (bool), counts ([complete, incomplete]).
 *
 * Developed by Rui Pratas for the Training Centre of the Portuguese Ministry of Foreign Affairs
 * (Centro de Formação do Ministério dos Negócios Estrangeiros).
 *
 * @package    local_advancedexport
 * @copyright  2026 Rui Pratas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class export_form extends \moodleform {
    /** Sheet keys, in output order. */
    public const SHEETS = ['summary', 'byuser', 'byoption', 'peroption'];

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $c = $this->_customdata;
        $anonymous = !empty($c['anonymous']);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        if ($anonymous) {
            $mform->addElement('html', \html_writer::div(
                get_string('anonymouswarning', 'local_advancedexport'),
                'alert alert-warning'
            ));
        }

        // 1. Questions to group by.
        $mform->addElement('header', 'h_group', get_string('section_group', 'local_advancedexport'));
        $mform->setExpanded('h_group');
        $mform->addElement('static', 'group_help', '', get_string('section_group_help', 'local_advancedexport'));
        foreach ($c['groupable'] as $qid => $q) {
            $mform->addElement('advcheckbox', 'g_' . $qid, '', $this->question_label($q), ['group' => 1]);
        }
        if (!$c['groupable']) {
            $mform->addElement('static', 'nogroupable', '', get_string('nogroupable', 'local_advancedexport'));
        }

        if (!$anonymous) {
            // 2. Respondent information columns.
            $mform->addElement('header', 'h_cols', get_string('section_columns', 'local_advancedexport'));
            $mform->setExpanded('h_cols');
            $mform->addElement('static', 'cols_help', '', get_string('section_columns_help', 'local_advancedexport'));
            $mform->addElement('html', '<h5 class="mt-3">' . get_string('columns_user', 'local_advancedexport') . '</h5>');
            foreach ($c['userfields'] as $key => $label) {
                $mform->addElement('advcheckbox', 'u_' . $key, '', $label, ['group' => 2]);
            }
            $mform->addElement(
                'advcheckbox',
                'c_submitted',
                '',
                get_string('col_submitted', 'local_advancedexport'),
                ['group' => 2]
            );
            $mform->addElement('html', '<h5 class="mt-3">' . get_string('columns_questions', 'local_advancedexport') . '</h5>');
            foreach ($c['questions'] as $qid => $q) {
                $mform->addElement('advcheckbox', 'q_' . $qid, '', $this->question_label($q), ['group' => 3]);
            }
            // Own toggle link: Moodle's add_checkbox_controller() resets the group on every page load,
            // which would discard the remembered choices.
            $mform->addElement('static', 'q_toggle', '', \html_writer::link(
                '#',
                get_string('selectallnone', 'local_advancedexport'),
                ['id' => 'local-advancedexport-toggle-q']
            ));
        }

        // 3. Sheets.
        $mform->addElement('header', 'h_sheets', get_string('section_sheets', 'local_advancedexport'));
        $mform->setExpanded('h_sheets');
        foreach (self::SHEETS as $key) {
            $attrs = [];
            if ($anonymous && $key !== 'summary') {
                $attrs['disabled'] = 'disabled';
            }
            $mform->addElement(
                'advcheckbox',
                's_' . $key,
                '',
                get_string('sheet_' . $key . '_desc', 'local_advancedexport'),
                $attrs
            );
        }

        // 4. Format and options.
        $mform->addElement('header', 'h_format', get_string('section_format', 'local_advancedexport'));
        $mform->setExpanded('h_format');
        $formats = [];
        foreach (array_keys(\local_advancedexport\local\writer::FORMATS) as $f) {
            $formats[$f] = get_string('format_' . $f, 'local_advancedexport');
        }
        $mform->addElement('select', 'format', get_string('format', 'local_advancedexport'), $formats);
        [$complete, $incomplete] = $c['counts'];
        $mform->addElement(
            'advcheckbox',
            'includeincomplete',
            get_string('includeincomplete', 'local_advancedexport'),
            get_string('includeincomplete_desc', 'local_advancedexport', $incomplete)
        );

        $this->add_action_buttons(true, get_string('export', 'local_advancedexport'));
    }

    /**
     * Checkbox label for a question: number, short name and an excerpt of its text.
     *
     * @param \stdClass $q
     * @return string
     */
    protected function question_label(\stdClass $q): string {
        $label = \html_writer::tag('strong', s($q->label));
        $text = $q->text;
        $same = \core_text::strtolower(trim((string)$q->name)) === \core_text::strtolower(trim($text));
        if ($text !== '' && trim((string)$q->name) !== '' && !$same) {
            $excerpt = \core_text::substr($text, 0, 110) . (\core_text::strlen($text) > 110 ? '…' : '');
            $label .= ' ' . \html_writer::span('— ' . s($excerpt), 'text-muted small');
        }
        return $label;
    }

    /**
     * Validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $c = $this->_customdata;
        $groups = array_filter(array_keys($c['groupable']), function ($qid) use ($data) {
            return !empty($data['g_' . $qid]);
        });
        if (!$groups) {
            $errors['group_help'] = get_string('error_nogroup', 'local_advancedexport');
        }
        $sheets = array_filter(self::SHEETS, function ($k) use ($data) {
            return !empty($data['s_' . $k]);
        });
        if (!$sheets && empty($c['anonymous'])) {
            $errors['s_summary'] = get_string('error_nosheet', 'local_advancedexport');
        }
        return $errors;
    }

    /**
     * Read the submitted choices into a plain structure.
     *
     * @param \stdClass $data
     * @return array ['groups' => int[], 'columns' => string[], 'sheets' => [key => bool], 'format' => string,
     *                'includeincomplete' => bool]
     */
    public function choices_from_data(\stdClass $data): array {
        $c = $this->_customdata;
        $choices = ['groups' => [], 'columns' => [], 'sheets' => [], 'format' => $data->format,
            'includeincomplete' => !empty($data->includeincomplete)];
        foreach (array_keys($c['groupable']) as $qid) {
            if (!empty($data->{'g_' . $qid})) {
                $choices['groups'][] = (int)$qid;
            }
        }
        foreach (array_keys($c['userfields']) as $key) {
            if (!empty($data->{'u_' . $key})) {
                $choices['columns'][] = 'u:' . $key;
            }
        }
        foreach (array_keys($c['questions']) as $qid) {
            if (!empty($data->{'q_' . $qid})) {
                $choices['columns'][] = 'q:' . $qid;
            }
        }
        if (!empty($data->c_submitted)) {
            $choices['columns'][] = 'submitted';
        }
        if ($choices['includeincomplete']) {
            $choices['columns'][] = 'status';
        }
        foreach (self::SHEETS as $key) {
            $choices['sheets'][$key] = !empty($data->{'s_' . $key});
        }
        return $choices;
    }

    /**
     * Turn saved choices back into form defaults.
     *
     * @param array $choices as returned by choices_from_data()
     * @return array
     */
    public static function defaults_from_choices(array $choices): array {
        $d = ['format' => $choices['format'] ?? 'xlsx', 'includeincomplete' => !empty($choices['includeincomplete'])];
        foreach ($choices['groups'] ?? [] as $qid) {
            $d['g_' . $qid] = 1;
        }
        foreach ($choices['columns'] ?? [] as $col) {
            if ($col === 'submitted') {
                $d['c_submitted'] = 1;
            } else if (strpos($col, 'u:') === 0) {
                $d['u_' . substr($col, 2)] = 1;
            } else if (strpos($col, 'q:') === 0) {
                $d['q_' . substr($col, 2)] = 1;
            }
        }
        foreach ($choices['sheets'] ?? [] as $key => $on) {
            $d['s_' . $key] = $on ? 1 : 0;
        }
        return $d;
    }
}
