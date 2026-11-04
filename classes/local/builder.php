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
 * Turns loaded responses into the sheets the user asked for.
 *
 * Developed by Rui Pratas for the Training Centre of the Portuguese Ministry of Foreign Affairs
 * (Centro de Formação do Ministério dos Negócios Estrangeiros).
 *
 * @package    local_advancedexport
 * @copyright  2026 Rui Pratas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class builder {
    /** Tick mark used in the per-question columns. */
    public const TICK = '✓';

    /** @var questionnaire_source */
    protected $source;
    /** @var int[] question ids to group by */
    protected $groupqids;
    /** @var string[] column keys, in order */
    protected $columns;
    /** @var bool */
    protected $anonymous;

    /**
     * Constructor.
     *
     * @param questionnaire_source $source responses must already be loaded
     * @param int[] $groupqids
     * @param string[] $columns
     */
    public function __construct(questionnaire_source $source, array $groupqids, array $columns) {
        $this->source = $source;
        $this->groupqids = array_values($groupqids);
        $this->anonymous = $source->is_anonymous();
        $this->columns = $this->anonymous ? [] : array_values($columns);
    }

    /**
     * Build the requested sheets, in a fixed order.
     *
     * @param array $which keys: summary, byuser, byoption, peroption (bool)
     * @return sheet[]
     */
    public function build(array $which): array {
        $sheets = [];
        if (!empty($which['summary']) || $this->anonymous) {
            $sheets[] = $this->summary();
        }
        if ($this->anonymous) {
            return $sheets;
        }
        if (!empty($which['byuser'])) {
            $sheets[] = $this->by_user();
        }
        if (!empty($which['byoption'])) {
            $sheets[] = $this->by_option();
        }
        if (!empty($which['peroption'])) {
            foreach ($this->per_option() as $s) {
                $sheets[] = $s;
            }
        }
        return $sheets;
    }

    /**
     * Header text of a column key.
     *
     * @param string $key
     * @return string
     */
    public function column_header(string $key): string {
        static $userfields = null;
        if ($key === 'submitted') {
            return get_string('col_submitted', 'local_advancedexport');
        }
        if ($key === 'status') {
            return get_string('col_status', 'local_advancedexport');
        }
        [$type, $id] = explode(':', $key, 2);
        if ($type === 'u') {
            if ($userfields === null) {
                $userfields = questionnaire_source::get_user_fields();
            }
            return $userfields[$id] ?? $id;
        }
        $questions = $this->source->get_questions();
        return isset($questions[$id]) ? $questions[$id]->label : $key;
    }

    /**
     * Values of the chosen columns for one response.
     *
     * @param int $respid
     * @return array
     */
    protected function info_cells(int $respid): array {
        $cells = [];
        foreach ($this->columns as $key) {
            $cells[] = $this->source->get_cell($respid, $key);
        }
        return $cells;
    }

    /**
     * Distinct options across the grouped questions, merged by label (case-insensitive),
     * in the order they first appear.
     *
     * @return array normalisedlabel => ['label' => string, 'keys' => [qid => optionkey]]
     */
    protected function merged_options(): array {
        $merged = [];
        foreach ($this->groupqids as $qid) {
            foreach ($this->source->get_options($qid) as $key => $label) {
                $norm = \core_text::strtolower(trim($label));
                if (!isset($merged[$norm])) {
                    $merged[$norm] = ['label' => $label, 'keys' => []];
                }
                $merged[$norm]['keys'][$qid] = (string)$key;
            }
        }
        return $merged;
    }

    /**
     * Responses that picked a merged option, with which question(s) they picked it in.
     *
     * @param array $option one entry of merged_options()
     * @return array respid => [qid => bool]
     */
    protected function respondents_of(array $option): array {
        $result = [];
        foreach ($this->source->get_response_ids() as $respid) {
            $hits = [];
            $any = false;
            foreach ($this->groupqids as $qid) {
                $hit = isset($option['keys'][$qid]) && array_key_exists(
                    $option['keys'][$qid],
                    $this->source->get_selected($respid, $qid)
                );
                $hits[$qid] = $hit;
                $any = $any || $hit;
            }
            if ($any) {
                $result[$respid] = $hits;
            }
        }
        return $result;
    }

    /**
     * Headers for the per-question tick columns (only when grouping by more than one question).
     *
     * @return string[]
     */
    protected function tick_headers(): array {
        if (count($this->groupqids) < 2) {
            return [];
        }
        $questions = $this->source->get_questions();
        $headers = [];
        foreach ($this->groupqids as $qid) {
            $headers[] = get_string('col_pickedin', 'local_advancedexport', $questions[$qid]->label);
        }
        return $headers;
    }

    /**
     * Tick cells for one respondent.
     *
     * @param array $hits qid => bool
     * @return array
     */
    protected function tick_cells(array $hits): array {
        if (count($this->groupqids) < 2) {
            return [];
        }
        $cells = [];
        foreach ($this->groupqids as $qid) {
            $cells[] = !empty($hits[$qid]) ? self::TICK : '';
        }
        return $cells;
    }

    /**
     * Sheet "By respondent": one row per response, chosen options joined per question.
     *
     * @return sheet
     */
    public function by_user(): sheet {
        $s = new sheet(get_string('sheet_byuser', 'local_advancedexport'));
        $questions = $this->source->get_questions();
        foreach ($this->columns as $key) {
            $s->headers[] = $this->column_header($key);
        }
        foreach ($this->groupqids as $qid) {
            $s->headers[] = $questions[$qid]->label;
        }
        foreach ($this->source->get_response_ids() as $respid) {
            $row = $this->info_cells($respid);
            foreach ($this->groupqids as $qid) {
                $row[] = implode("\n", $this->source->get_selected($respid, $qid)); // One option per line.
            }
            $s->rows[] = $row;
        }
        return $s;
    }

    /**
     * Sheet "By option": option cell merged vertically, one row per respondent.
     *
     * @return sheet
     */
    public function by_option(): sheet {
        $s = new sheet(get_string('sheet_byoption', 'local_advancedexport'), sheet::KIND_GROUPED);
        $s->headers[] = get_string('col_option', 'local_advancedexport');
        foreach ($this->columns as $key) {
            $s->headers[] = $this->column_header($key);
        }
        $ticks = $this->tick_headers();
        $firsttick = count($s->headers);
        $s->headers = array_merge($s->headers, $ticks);
        $s->centercols = $ticks ? range($firsttick, count($s->headers) - 1) : [];

        foreach ($this->merged_options() as $option) {
            $people = $this->respondents_of($option);
            if (!$people) {
                continue;
            }
            $first = count($s->rows);
            foreach ($people as $respid => $hits) {
                $s->rows[] = array_merge([$option['label']], $this->info_cells($respid), $this->tick_cells($hits));
            }
            $s->groups[] = [$option['label'], $first, count($s->rows) - 1];
        }
        return $s;
    }

    /**
     * One sheet per option.
     *
     * @return sheet[]
     */
    public function per_option(): array {
        $sheets = [];
        $headers = [];
        foreach ($this->columns as $key) {
            $headers[] = $this->column_header($key);
        }
        $ticks = $this->tick_headers();
        $firsttick = count($headers);
        $headers = array_merge($headers, $ticks);
        foreach ($this->merged_options() as $option) {
            $people = $this->respondents_of($option);
            if (!$people) {
                continue;
            }
            $s = new sheet($option['label']);
            $s->headers = $headers;
            $s->centercols = $ticks ? range($firsttick, count($headers) - 1) : [];
            foreach ($people as $respid => $hits) {
                $s->rows[] = array_merge($this->info_cells($respid), $this->tick_cells($hits));
            }
            // Tab names are cut to 31 characters, so repeat the full option name under the table.
            $s->notes[] = get_string('col_option', 'local_advancedexport') . ': ' . $option['label'];
            $s->notes[] = get_string('note_count', 'local_advancedexport', count($people));
            $sheets[] = $s;
        }
        return $sheets;
    }

    /**
     * Sheet "Summary": question cell merged, count and share of responses per option.
     *
     * @return sheet
     */
    public function summary(): sheet {
        $s = new sheet(get_string('sheet_summary', 'local_advancedexport'), sheet::KIND_GROUPED);
        $s->headers = [
            get_string('col_question', 'local_advancedexport'),
            get_string('col_option', 'local_advancedexport'),
            get_string('col_count', 'local_advancedexport'),
            get_string('col_percent', 'local_advancedexport'),
        ];
        $s->centercols = [2, 3];
        $s->pctcols = [3];
        $respids = $this->source->get_response_ids();
        $total = count($respids);
        $questions = $this->source->get_questions();
        foreach ($this->groupqids as $qid) {
            $first = count($s->rows);
            foreach ($this->source->get_options($qid) as $key => $label) {
                $n = 0;
                foreach ($respids as $respid) {
                    if (array_key_exists($key, $this->source->get_selected($respid, $qid))) {
                        $n++;
                    }
                }
                $s->rows[] = [$questions[$qid]->label, $label, $n, $total ? $n / $total : 0];
            }
            if (count($s->rows) > $first) {
                $s->groups[] = [$questions[$qid]->label, $first, count($s->rows) - 1];
            }
        }
        $s->notes[] = get_string('note_total', 'local_advancedexport', $total);
        $s->notes[] = get_string('note_multiple', 'local_advancedexport');
        if ($this->anonymous) {
            $s->notes[] = get_string('note_anonymous', 'local_advancedexport');
        }
        return $s;
    }
}
