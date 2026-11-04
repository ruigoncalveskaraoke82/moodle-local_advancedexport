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

namespace local_advancedexport\local;

/**
 * Reads the questions and responses of one mod_questionnaire instance straight from its tables.
 *
 * Read-only: never writes to the Questionnaire tables.
 *
 * Developed by Rui Pratas for the Training Centre of the Portuguese Ministry of Foreign Affairs
 * (Centro de Formação do Ministério dos Negócios Estrangeiros).
 *
 * @package    local_advancedexport
 * @copyright  2026 Rui Pratas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class questionnaire_source {
    /** Question type ids, as defined in questionnaire_question_type. */
    public const TYPE_YESNO = 1;
    /** Text box. */
    public const TYPE_TEXT = 2;
    /** Essay. */
    public const TYPE_ESSAY = 3;
    /** Radio buttons. */
    public const TYPE_RADIO = 4;
    /** Check boxes. */
    public const TYPE_CHECKBOX = 5;
    /** Dropdown. */
    public const TYPE_DROPDOWN = 6;
    /** Rate (scale). */
    public const TYPE_RATE = 8;
    /** Date. */
    public const TYPE_DATE = 9;
    /** Numeric. */
    public const TYPE_NUMERIC = 10;
    /** Slider. */
    public const TYPE_SLIDER = 11;
    /** Page break (no answer). */
    public const TYPE_PAGEBREAK = 99;
    /** Section text (no answer). */
    public const TYPE_SECTIONTEXT = 100;

    /** Question types whose answers are options that can be grouped. */
    public const GROUPABLE_TYPES = [self::TYPE_YESNO, self::TYPE_RADIO, self::TYPE_CHECKBOX, self::TYPE_DROPDOWN];

    /** @var \stdClass course module */
    protected $cm;
    /** @var \stdClass questionnaire record */
    protected $questionnaire;
    /** @var array qid => question record (answerable questions only, in survey order), with ->number and ->label */
    protected $questions = [];
    /** @var array qid => [optionkey => label] */
    protected $options = [];
    /** @var array qid => [choiceid => true] for "other" choices */
    protected $otherchoices = [];
    /** @var array responseid => response record (with ->user) */
    protected $responses = [];
    /** @var array responseid => qid => ['sel' => [optionkey...], 'text' => [string...], 'other' => [choiceid => string]] */
    protected $answers = [];
    /** @var array userid => [profilefieldshortname => value] */
    protected $profiledata = [];

    /**
     * Constructor.
     *
     * @param \stdClass $cm
     * @param \stdClass $questionnaire
     */
    public function __construct(\stdClass $cm, \stdClass $questionnaire) {
        $this->cm = $cm;
        $this->questionnaire = $questionnaire;
        $this->load_questions();
    }

    /**
     * Activity name.
     *
     * @return string
     */
    public function get_name(): string {
        return format_string($this->questionnaire->name, true, ['context' => \context_module::instance($this->cm->id)]);
    }

    /**
     * Whether the questionnaire hides respondents' identity.
     *
     * @return bool
     */
    public function is_anonymous(): bool {
        return $this->questionnaire->respondenttype === 'anonymous';
    }

    /**
     * Number of responses, complete and incomplete.
     *
     * @return array [complete, incomplete]
     */
    public function count_responses(): array {
        global $DB;
        $complete = $DB->count_records('questionnaire_response', [
            'questionnaireid' => $this->questionnaire->id,
            'complete' => 'y',
        ]);
        $all = $DB->count_records('questionnaire_response', ['questionnaireid' => $this->questionnaire->id]);
        return [$complete, $all - $complete];
    }

    /**
     * Turn stored rich text into a single plain line.
     *
     * @param string|null $text
     * @return string
     */
    public static function plain($text): string {
        $text = html_to_text((string)$text, 0, false);
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim($text);
    }

    /**
     * SQL condition for questions that are not deleted.
     *
     * Questionnaire 2024080100 (and older) stores "deleted" as 'y'/'n'. From 2024080100.01 it is
     * a timestamp, NULL for live questions. The column type tells which one is installed.
     *
     * @param array $params named parameters, extended when needed
     * @return string
     */
    public static function not_deleted_sql(array &$params): string {
        global $DB;
        $columns = $DB->get_columns('questionnaire_question');
        $type = isset($columns['deleted']) ? $columns['deleted']->meta_type : 'C';
        if ($type === 'I' || $type === 'N') {
            return 'deleted IS NULL';
        }
        $params['notdeleted'] = 'n';
        return '(deleted IS NULL OR deleted = :notdeleted)';
    }

    /**
     * Load the answerable questions and their options.
     */
    protected function load_questions(): void {
        global $DB;
        $params = ['sid' => $this->questionnaire->sid, 'pb' => self::TYPE_PAGEBREAK, 'st' => self::TYPE_SECTIONTEXT];
        $records = $DB->get_records_select(
            'questionnaire_question',
            'surveyid = :sid AND ' . self::not_deleted_sql($params) . ' AND type_id NOT IN (:pb, :st)',
            $params,
            'position ASC, id ASC'
        );
        $number = 0;
        foreach ($records as $q) {
            $number++;
            $q->number = $number;
            $name = trim((string)$q->name);
            $text = self::plain($q->content);
            $q->text = $text;
            $short = $name !== '' ? $name : \core_text::substr($text, 0, 60) . (\core_text::strlen($text) > 60 ? '…' : '');
            $q->label = get_string('questionprefix', 'local_advancedexport', $number) . ' · ' . $short;
            $this->questions[$q->id] = $q;
            $this->options[$q->id] = [];
            $this->otherchoices[$q->id] = [];
        }
        if (!$this->questions) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal(array_keys($this->questions), SQL_PARAMS_NAMED);
        $choices = $DB->get_records_select('questionnaire_quest_choice', "question_id $insql", $params, 'id ASC');
        foreach ($choices as $c) {
            $content = (string)$c->content;
            if (strpos($content, '!other') === 0) {
                $label = preg_replace(['/^!other=/', '/^!other/'], ['', get_string('other', 'questionnaire')], $content);
                $this->otherchoices[$c->question_id][$c->id] = true;
            } else {
                $label = $content;
            }
            $this->options[$c->question_id][$c->id] = self::plain(format_string($label));
        }
        foreach ($this->questions as $q) {
            if ((int)$q->type_id === self::TYPE_YESNO) {
                $this->options[$q->id] = ['y' => get_string('yes'), 'n' => get_string('no')];
            }
        }
    }

    /**
     * All answerable questions.
     *
     * @return array qid => question
     */
    public function get_questions(): array {
        return $this->questions;
    }

    /**
     * Questions whose answers are options (check boxes, radio, dropdown, yes/no).
     *
     * @return array qid => question
     */
    public function get_groupable_questions(): array {
        return array_filter($this->questions, function ($q) {
            return in_array((int)$q->type_id, self::GROUPABLE_TYPES);
        });
    }

    /**
     * Options of a question, in their defined order.
     *
     * @param int $qid
     * @return array optionkey => label
     */
    public function get_options(int $qid): array {
        return $this->options[$qid] ?? [];
    }

    /**
     * Identity fields that can be added as columns: core user fields plus custom profile fields.
     *
     * @return array key => label
     */
    public static function get_user_fields(): array {
        global $DB;
        $fields = [
            'fullname' => get_string('fullnameuser'),
            'firstname' => get_string('firstname'),
            'lastname' => get_string('lastname'),
            'email' => get_string('email'),
            'username' => get_string('username'),
            'idnumber' => get_string('idnumber'),
            'institution' => get_string('institution'),
            'department' => get_string('department'),
            'phone1' => get_string('phone1'),
            'city' => get_string('city'),
            'country' => get_string('country'),
        ];
        foreach ($DB->get_records('user_info_field', null, 'sortorder ASC', 'id, shortname, name') as $f) {
            $fields['profile_' . $f->shortname] = format_string($f->name);
        }
        return $fields;
    }

    /**
     * Load responses and answers into memory.
     *
     * @param bool $includeincomplete include saved-but-not-submitted responses
     */
    public function load_responses(bool $includeincomplete): void {
        global $DB;
        $params = ['qid' => $this->questionnaire->id];
        $where = 'r.questionnaireid = :qid';
        if (!$includeincomplete) {
            $where .= ' AND r.complete = :y';
            $params['y'] = 'y';
        }
        $namefields = \core_user\fields::for_name()->get_sql('u', false, 'u_', '', true);
        $sql = "SELECT r.id, r.submitted, r.complete, r.userid {$namefields->selects},
                       u.email AS u_email, u.username AS u_username, u.idnumber AS u_idnumber,
                       u.institution AS u_institution, u.department AS u_department, u.phone1 AS u_phone1,
                       u.city AS u_city, u.country AS u_country
                  FROM {questionnaire_response} r
             LEFT JOIN {user} u ON u.id = r.userid
                 WHERE $where
              ORDER BY r.id ASC";
        $rows = $DB->get_records_sql($sql, $params);

        $this->responses = [];
        $userids = [];
        foreach ($rows as $row) {
            $user = new \stdClass();
            foreach ((array)$row as $k => $v) {
                if (strpos($k, 'u_') === 0) {
                    $user->{substr($k, 2)} = $v;
                }
            }
            $user->id = (int)$row->userid;
            $row->user = $user;
            $this->responses[$row->id] = $row;
            $this->answers[$row->id] = [];
            if ($user->id) {
                $userids[$user->id] = $user->id;
            }
        }
        if (!$this->responses) {
            return;
        }

        $base = "FROM {%s} a JOIN {questionnaire_response} r ON r.id = a.response_id WHERE $where";
        // Option answers (checkbox, radio, dropdown).
        foreach (['questionnaire_resp_multiple', 'questionnaire_resp_single'] as $table) {
            $rs = $DB->get_recordset_sql("SELECT a.id, a.response_id, a.question_id, a.choice_id " .
                sprintf($base, $table) . " ORDER BY a.id", $params);
            foreach ($rs as $a) {
                $this->add_selection($a->response_id, $a->question_id, $a->choice_id);
            }
            $rs->close();
        }
        // Yes/No.
        $rs = $DB->get_recordset_sql("SELECT a.id, a.response_id, a.question_id, a.choice_id " .
            sprintf($base, 'questionnaire_response_bool'), $params);
        foreach ($rs as $a) {
            $this->add_selection($a->response_id, $a->question_id, (string)$a->choice_id);
        }
        $rs->close();
        // Free text typed in an "other" choice.
        $rs = $DB->get_recordset_sql("SELECT a.id, a.response_id, a.question_id, a.choice_id, a.response " .
            sprintf($base, 'questionnaire_response_other'), $params);
        foreach ($rs as $a) {
            if (isset($this->answers[$a->response_id])) {
                $this->answers[$a->response_id][$a->question_id]['other'][$a->choice_id] = self::plain($a->response);
            }
        }
        $rs->close();
        // Text, essay, numeric, slider and dates.
        foreach (['questionnaire_response_text', 'questionnaire_response_date'] as $table) {
            $rs = $DB->get_recordset_sql("SELECT a.id, a.response_id, a.question_id, a.response " .
                sprintf($base, $table), $params);
            foreach ($rs as $a) {
                if (isset($this->answers[$a->response_id])) {
                    $this->answers[$a->response_id][$a->question_id]['text'][] = self::plain($a->response);
                }
            }
            $rs->close();
        }
        // Rate questions: "option: value".
        $rs = $DB->get_recordset_sql("SELECT a.id, a.response_id, a.question_id, a.choice_id, a.rankvalue " .
            sprintf($base, 'questionnaire_response_rank'), $params);
        foreach ($rs as $a) {
            if (!isset($this->answers[$a->response_id])) {
                continue;
            }
            $label = $this->options[$a->question_id][$a->choice_id] ?? '';
            $value = ((int)$a->rankvalue < 0) ? get_string('notapplicable', 'questionnaire') : (int)$a->rankvalue;
            $this->answers[$a->response_id][$a->question_id]['text'][] = trim("$label: $value", ': ');
        }
        $rs->close();

        // Custom profile field values.
        if ($userids) {
            [$insql, $inparams] = $DB->get_in_or_equal(array_values($userids), SQL_PARAMS_NAMED);
            $sql = "SELECT d.id, d.userid, f.shortname, d.data
                      FROM {user_info_data} d
                      JOIN {user_info_field} f ON f.id = d.fieldid
                     WHERE d.userid $insql";
            foreach ($DB->get_records_sql($sql, $inparams) as $d) {
                $this->profiledata[$d->userid][$d->shortname] = self::plain($d->data);
            }
        }
    }

    /**
     * Record that a response selected an option.
     *
     * @param int $respid
     * @param int $qid
     * @param string|int $key
     */
    protected function add_selection($respid, $qid, $key): void {
        if (!isset($this->answers[$respid]) || !isset($this->questions[$qid])) {
            return;
        }
        $this->answers[$respid][$qid]['sel'][] = (string)$key;
    }

    /**
     * Loaded response ids, sorted by respondent name (or by order of submission when anonymous).
     *
     * @return int[]
     */
    public function get_response_ids(): array {
        if ($this->is_anonymous()) {
            return array_keys($this->responses);
        }
        $names = [];
        foreach ($this->responses as $id => $r) {
            $names[$id] = $r->user->id ? fullname($r->user) : '';
        }
        \core_collator::asort($names, \core_collator::SORT_NATURAL);
        return array_keys($names);
    }

    /**
     * Options selected by a response for a question, in the question's option order.
     *
     * @param int $respid
     * @param int $qid
     * @return array optionkey => label
     */
    public function get_selected(int $respid, int $qid): array {
        $sel = $this->answers[$respid][$qid]['sel'] ?? [];
        $result = [];
        foreach ($this->get_options($qid) as $key => $label) {
            if (in_array((string)$key, $sel, true)) {
                $result[$key] = $label;
            }
        }
        return $result;
    }

    /**
     * The value of a column for a response.
     *
     * Column keys: "u:<userfield>", "q:<questionid>", "submitted", "status".
     *
     * @param int $respid
     * @param string $colkey
     * @return string
     */
    public function get_cell(int $respid, string $colkey): string {
        $r = $this->responses[$respid];
        if ($colkey === 'submitted') {
            return $r->submitted ? userdate($r->submitted, get_string('strftimedatetimeshort', 'langconfig')) : '';
        }
        if ($colkey === 'status') {
            return $r->complete === 'y' ? get_string('status_complete', 'local_advancedexport')
                : get_string('status_incomplete', 'local_advancedexport');
        }
        [$type, $key] = explode(':', $colkey, 2);
        if ($type === 'u') {
            if ($this->is_anonymous() || !$r->user->id) {
                return '';
            }
            if ($key === 'fullname') {
                return fullname($r->user);
            }
            if ($key === 'country') {
                $c = (string)($r->user->country ?? '');
                return $c !== '' ? get_string($c, 'countries') : '';
            }
            if (strpos($key, 'profile_') === 0) {
                return $this->profiledata[$r->user->id][substr($key, 8)] ?? '';
            }
            return (string)($r->user->$key ?? '');
        }
        if ($type === 'q') {
            return $this->get_answer_text($respid, (int)$key);
        }
        return '';
    }

    /**
     * An answer as readable text.
     *
     * @param int $respid
     * @param int $qid
     * @return string
     */
    public function get_answer_text(int $respid, int $qid): string {
        $answer = $this->answers[$respid][$qid] ?? [];
        $parts = [];
        foreach ($this->get_selected($respid, $qid) as $key => $label) {
            if (isset($this->otherchoices[$qid][$key]) && !empty($answer['other'][$key])) {
                $label .= ' ' . $answer['other'][$key];
            }
            $parts[] = $label;
        }
        foreach ($answer['text'] ?? [] as $t) {
            if ($t !== '') {
                $parts[] = $t;
            }
        }
        return implode('; ', $parts);
    }
}
