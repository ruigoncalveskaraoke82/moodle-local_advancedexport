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
 * A format-independent table: what every writer (xlsx, ods, csv, pdf) receives.
 *
 * Developed by Rui Pratas for the Training Centre of the Portuguese Ministry of Foreign Affairs
 * (Centro de Formação do Ministério dos Negócios Estrangeiros).
 *
 * @package    local_advancedexport
 * @copyright  2026 Rui Pratas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sheet {
    /** Plain list with filters. */
    public const KIND_LIST = 'list';
    /** Grouped list: first column merged per group. */
    public const KIND_GROUPED = 'grouped';

    /** @var string tab name */
    public $name;
    /** @var string kind of table */
    public $kind = self::KIND_LIST;
    /** @var string[] column headers */
    public $headers = [];
    /** @var array rows of scalar values */
    public $rows = [];
    /** @var array groups for KIND_GROUPED: [label, firstrow, lastrow] (0-based data rows) */
    public $groups = [];
    /** @var int[] columns to centre (0-based) */
    public $centercols = [];
    /** @var int[] columns holding fractions shown as percentages */
    public $pctcols = [];
    /** @var string[] notes printed under the table */
    public $notes = [];

    /**
     * Constructor.
     *
     * @param string $name
     * @param string $kind
     */
    public function __construct(string $name, string $kind = self::KIND_LIST) {
        $this->name = $name;
        $this->kind = $kind;
    }

    /**
     * Group index (0-based) of each data row, or null when the row is in no group.
     *
     * @return array rowindex => groupindex
     */
    public function row_groups(): array {
        $map = [];
        foreach ($this->groups as $g => [$label, $first, $last]) {
            for ($r = $first; $r <= $last; $r++) {
                $map[$r] = $g;
            }
        }
        return $map;
    }
}
