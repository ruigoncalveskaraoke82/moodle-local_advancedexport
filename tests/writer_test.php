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

use local_advancedexport\local\sheet;
use local_advancedexport\local\writer;

/**
 * Tests for the file writers.
 *
 * @package    local_advancedexport
 * @category   test
 * @copyright  2026 Rui Pratas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_advancedexport\local\writer
 */
final class writer_test extends \advanced_testcase {
    /**
     * Values that a spreadsheet would run as a formula, with what the CSV must contain instead.
     *
     * @return array
     */
    public static function formula_provider(): array {
        return [
            'equals' => ['=HYPERLINK("http://example.com","x")', '\'=HYPERLINK("http://example.com","x")'],
            'plus' => ['+1+1', "'+1+1"],
            'minus' => ['-2+3', "'-2+3"],
            'at' => ['@SUM(A1:A2)', "'@SUM(A1:A2)"],
            'tab' => ["\t=1+1", "'\t=1+1"],
            'carriage return' => ["\r=1+1", "'\r=1+1"],
        ];
    }

    /**
     * Each dangerous first character is neutralised with a leading apostrophe.
     *
     * @dataProvider formula_provider
     * @param string $input
     * @param string $expected
     */
    public function test_csv_cell_neutralises_formulas(string $input, string $expected): void {
        $this->assertSame($expected, writer::csv_cell($input));
    }

    /**
     * Ordinary text, numbers and line breaks are handled without adding an apostrophe.
     */
    public function test_csv_cell_leaves_safe_values(): void {
        $this->assertSame('English', writer::csv_cell('English'));
        $this->assertSame('', writer::csv_cell(''));
        $this->assertSame('a = b', writer::csv_cell('a = b'));
        $this->assertSame('French; German', writer::csv_cell("French\nGerman"));
        $this->assertSame(-5, writer::csv_cell(-5));
        $this->assertSame(0.25, writer::csv_cell(0.25));
    }

    /**
     * The written CSV file neutralises formulas in headers and in data cells.
     */
    public function test_csv_file_neutralises_headers_and_cells(): void {
        $this->resetAfterTest();
        $s = new sheet('Summary');
        $s->headers = ['=Name', 'Answer'];
        $s->rows = [
            ['Ana', '=cmd|\' /C calc\'!A0'],
            ['Bruno', '@SUM(1,2)'],
            ['Carla', 'plain text'],
            ['Dora', -3],
        ];
        [$path] = writer::write([$s], 'csv', 'test', 'Test');
        $content = file_get_contents($path);
        $rows = array_map(
            fn($line) => str_getcsv($line, ';'),
            preg_split('/\r?\n/', trim(substr($content, 3)))
        );

        $this->assertSame(["'=Name", 'Answer'], $rows[0]);
        $this->assertSame("'=cmd|' /C calc'!A0", $rows[1][1]);
        $this->assertSame("'@SUM(1,2)", $rows[2][1]);
        $this->assertSame('plain text', $rows[3][1]);
        $this->assertSame('-3', $rows[4][1]);
    }
}
