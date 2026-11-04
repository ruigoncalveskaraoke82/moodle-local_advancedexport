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

namespace local_advancedexport;

use local_advancedexport\local\builder;
use local_advancedexport\local\questionnaire_source;
use local_advancedexport\local\sheet;
use local_advancedexport\local\writer;

/**
 * Tests for reading Questionnaire responses and building the export sheets.
 *
 * @package    local_advancedexport
 * @category   test
 * @copyright  2026 Rui Pratas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_advancedexport\local\questionnaire_source
 * @covers     \local_advancedexport\local\builder
 * @covers     \local_advancedexport\local\writer
 */
final class builder_test extends \advanced_testcase {
    /** @var \stdClass course module */
    private $cm;
    /** @var int[] the two check box question ids */
    private $qids = [];
    /** @var \stdClass[] users who answered */
    private $users = [];

    /**
     * Create a questionnaire with two check box questions sharing the same options, and four responses.
     *
     * Ana: Q1 English, French; Q2 French. Bruno: Q1 English. Carla: Q2 German. Dora: Q1 French, not submitted.
     *
     * @param string $respondenttype fullname or anonymous
     */
    private function create_data(string $respondenttype = 'fullname'): void {
        global $DB;
        // Loads the question type constants used by the Questionnaire generator.
        class_exists('\mod_questionnaire\question\question');

        $course = $this->getDataGenerator()->create_course();
        $qgen = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $questionnaire = $qgen->create_instance(['course' => $course->id, 'respondenttype' => $respondenttype]);
        $this->cm = get_coursemodule_from_instance('questionnaire', $questionnaire->id);
        $options = ['English', 'French', 'German'];
        foreach (['NEED', 'WISH'] as $name) {
            $question = $qgen->create_question($questionnaire, [
                'surveyid' => $questionnaire->sid,
                'type_id' => 5,
                'name' => $name,
                'content' => "<p>$name languages</p>",
            ], $options);
            $this->qids[] = (int)$question->id;
        }
        $choices = [];
        foreach ($this->qids as $qid) {
            foreach ($DB->get_records('questionnaire_quest_choice', ['question_id' => $qid], 'id') as $c) {
                $choices[$qid][$c->content] = $c->id;
            }
        }
        $answers = [
            'Ana' => [[$this->qids[0], 'English'], [$this->qids[0], 'French'], [$this->qids[1], 'French']],
            'Bruno' => [[$this->qids[0], 'English']],
            'Carla' => [[$this->qids[1], 'German']],
            'Dora' => [[$this->qids[0], 'French']],
        ];
        foreach ($answers as $firstname => $picked) {
            $user = $this->getDataGenerator()->create_user(['firstname' => $firstname, 'lastname' => 'Test']);
            $this->users[$firstname] = $user;
            $rid = $DB->insert_record('questionnaire_response', [
                'questionnaireid' => $questionnaire->id,
                'userid' => $user->id,
                'submitted' => time(),
                'complete' => $firstname === 'Dora' ? 'n' : 'y',
                'grade' => 0,
            ]);
            foreach ($picked as [$qid, $label]) {
                $DB->insert_record('questionnaire_resp_multiple', [
                    'response_id' => $rid,
                    'question_id' => $qid,
                    'choice_id' => $choices[$qid][$label],
                ]);
            }
        }
    }

    /**
     * A loaded source for the test questionnaire.
     *
     * @param bool $includeincomplete
     * @return questionnaire_source
     */
    private function source(bool $includeincomplete = false): questionnaire_source {
        global $DB;
        $source = new questionnaire_source($this->cm, $DB->get_record('questionnaire', ['id' => $this->cm->instance]));
        $source->load_responses($includeincomplete);
        return $source;
    }

    /**
     * Summary counts only submitted responses, per question and option.
     */
    public function test_summary_counts(): void {
        $this->resetAfterTest();
        $this->create_data();
        $builder = new builder($this->source(), $this->qids, ['u:fullname']);
        $summary = $builder->summary();

        $counts = [];
        foreach ($summary->rows as $row) {
            $counts[$row[0]][$row[1]] = $row[2];
        }
        $this->assertEquals(['English' => 2, 'French' => 1, 'German' => 0], array_values($counts)[0]);
        $this->assertEquals(['English' => 0, 'French' => 1, 'German' => 1], array_values($counts)[1]);
        $this->assertCount(2, $summary->groups);
    }

    /**
     * "By option" merges equal options across questions and ticks where each was chosen.
     */
    public function test_by_option_groups_and_ticks(): void {
        $this->resetAfterTest();
        $this->create_data();
        $builder = new builder($this->source(), $this->qids, ['u:firstname']);
        $sheet = $builder->by_option();

        $this->assertEquals(sheet::KIND_GROUPED, $sheet->kind);
        $groups = [];
        foreach ($sheet->groups as [$label, $first, $last]) {
            $groups[$label] = array_slice($sheet->rows, $first, $last - $first + 1);
        }
        $this->assertEquals(['English', 'French', 'German'], array_keys($groups));
        // French: Ana ticked in both questions.
        $this->assertEquals([['French', 'Ana', builder::TICK, builder::TICK]], $groups['French']);
        // English: Ana and Bruno, both only in the first question.
        $this->assertEquals(['Ana', 'Bruno'], array_column($groups['English'], 1));
        $this->assertEquals([builder::TICK, builder::TICK], array_column($groups['English'], 2));
        $this->assertEquals(['', ''], array_column($groups['English'], 3));
    }

    /**
     * Unsubmitted responses are left out unless asked for, and then get a status column.
     */
    public function test_incomplete_responses(): void {
        $this->resetAfterTest();
        $this->create_data();
        $this->assertCount(3, (new builder($this->source(false), $this->qids, ['u:fullname']))->by_user()->rows);

        $sheet = (new builder($this->source(true), $this->qids, ['u:firstname', 'status']))->by_user();
        $this->assertCount(4, $sheet->rows);
        $dora = array_values(array_filter($sheet->rows, fn($r) => $r[0] === 'Dora'))[0];
        $this->assertEquals(get_string('status_incomplete', 'local_advancedexport'), $dora[1]);
    }

    /**
     * Anonymous questionnaires only ever produce the summary, without names.
     */
    public function test_anonymous_only_summary(): void {
        $this->resetAfterTest();
        $this->create_data('anonymous');
        $builder = new builder($this->source(), $this->qids, ['u:fullname', 'u:email']);
        $sheets = $builder->build(['summary' => true, 'byuser' => true, 'byoption' => true, 'peroption' => true]);

        $this->assertCount(1, $sheets);
        $this->assertEquals(get_string('sheet_summary', 'local_advancedexport'), $sheets[0]->name);
        $text = json_encode($sheets);
        foreach ($this->users as $user) {
            $this->assertStringNotContainsString($user->firstname, $text);
            $this->assertStringNotContainsString($user->email, $text);
        }
    }

    /**
     * Deleted questions are not offered.
     */
    public function test_deleted_questions_ignored(): void {
        global $DB;
        $this->resetAfterTest();
        $this->create_data();
        $columns = $DB->get_columns('questionnaire_question');
        $deleted = in_array($columns['deleted']->meta_type, ['I', 'N']) ? time() : 'y';
        $DB->set_field('questionnaire_question', 'deleted', $deleted, ['id' => $this->qids[1]]);

        $source = $this->source();
        $this->assertEquals([$this->qids[0]], array_keys($source->get_questions()));
    }

    /**
     * Sheet names are valid for Excel and unique.
     */
    public function test_sheet_names(): void {
        $this->resetAfterTest();
        $used = [];
        $this->assertEquals('Advanced Excel', writer::sheet_name('Advanced Excel | 14h | 09/02/2026', $used));
        $this->assertEquals('Advanced Excel (2)', writer::sheet_name('Advanced Excel │ other dates', $used));
        $long = writer::sheet_name('A very long course name that does not fit [in] a tab: really', $used);
        $this->assertLessThanOrEqual(31, \core_text::strlen($long));
        $this->assertDoesNotMatchRegularExpression('/[\[\]:*?\/\\\\]/', $long);
    }

    /**
     * Every format produces a non-empty file.
     */
    public function test_all_formats_write(): void {
        $this->resetAfterTest();
        $this->create_data();
        $builder = new builder($this->source(), $this->qids, ['u:fullname']);
        $sheets = $builder->build(['summary' => true, 'byuser' => true, 'byoption' => true, 'peroption' => true]);
        foreach (array_keys(writer::FORMATS) as $format) {
            [$path, $filename] = writer::write($sheets, $format, 'Test export', 'Test');
            $this->assertFileExists($path);
            $this->assertGreaterThan(100, filesize($path), $format);
            $this->assertStringStartsWith('Test export', $filename);
        }
    }
}
