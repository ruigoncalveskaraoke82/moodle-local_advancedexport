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

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

/**
 * Writes sheets to a temporary file in the chosen format.
 *
 * Uses only libraries shipped with Moodle: PhpSpreadsheet (xlsx, ods), csv and TCPDF (pdf).
 *
 * Developed by Rui Pratas for the Training Centre of the Portuguese Ministry of Foreign Affairs
 * (Centro de Formação do Ministério dos Negócios Estrangeiros).
 *
 * @package    local_advancedexport
 * @copyright  2026 Rui Pratas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class writer {
    /** Available formats: key => file extension. */
    public const FORMATS = ['xlsx' => 'xlsx', 'ods' => 'ods', 'csv' => 'csv', 'pdf' => 'pdf'];

    /** Header background. */
    public const C_HEADER = '1F3864';
    /** Alternate group background. */
    public const C_BAND = 'EEF2F8';
    /** Light border. */
    public const C_LINE = 'D0D7E5';

    /**
     * Write the sheets.
     *
     * @param sheet[] $sheets
     * @param string $format one of FORMATS
     * @param string $basename file name without extension
     * @param string $title document title (PDF)
     * @return array [temporary path, download file name]
     */
    public static function write(array $sheets, string $format, string $basename, string $title): array {
        $dir = make_request_directory();
        $basename = clean_filename($basename);
        switch ($format) {
            case 'xlsx':
            case 'ods':
                $path = $dir . '/' . $basename . '.' . $format;
                self::write_spreadsheet($sheets, $path, $format === 'xlsx' ? 'Xlsx' : 'Ods');
                return [$path, $basename . '.' . $format];
            case 'csv':
                return self::write_csv($sheets, $dir, $basename);
            case 'pdf':
                $path = $dir . '/' . $basename . '.pdf';
                self::write_pdf($sheets, $path, $title);
                return [$path, $basename . '.pdf'];
        }
        throw new \coding_exception('Unknown format ' . $format);
    }

    /**
     * A valid, unique worksheet name (max 31 chars, no []:*?/\).
     *
     * @param string $name
     * @param array $used names already taken (lowercase => true)
     * @return string
     */
    public static function sheet_name(string $name, array &$used): string {
        // Keep only the part before a separator such as "|" (e.g. "Advanced Excel | 14h | dates").
        $parts = preg_split('/\s*[|│]\s*/u', $name);
        if (trim($parts[0]) !== '') {
            $name = $parts[0];
        }
        $name = trim(preg_replace('/[\[\]:*?\/\\\\]+/u', ' ', $name), " '");
        if ($name === '') {
            $name = get_string('sheet_default', 'local_advancedexport');
        }
        $base = rtrim(\core_text::substr($name, 0, 31));
        $candidate = $base;
        $i = 2;
        while (isset($used[\core_text::strtolower($candidate)])) {
            $suffix = " ($i)";
            $candidate = rtrim(\core_text::substr($base, 0, 31 - \core_text::strlen($suffix))) . $suffix;
            $i++;
        }
        $used[\core_text::strtolower($candidate)] = true;
        return $candidate;
    }

    /**
     * Excel / LibreOffice workbook with merged cells, colours, frozen header and filters.
     *
     * @param sheet[] $sheets
     * @param string $path
     * @param string $type Xlsx or Ods
     */
    protected static function write_spreadsheet(array $sheets, string $path, string $type): void {
        global $CFG;
        require_once($CFG->libdir . '/excellib.class.php'); // Makes sure PhpSpreadsheet and its dependencies autoload.

        $book = new Spreadsheet();
        $book->removeSheetByIndex(0);
        $book->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);
        $used = [];

        foreach ($sheets as $s) {
            $ws = $book->createSheet();
            $ws->setTitle(self::sheet_name($s->name, $used));
            $ncols = count($s->headers);
            if ($ncols === 0) {
                continue;
            }
            $lastcol = Coordinate::stringFromColumnIndex($ncols);

            // Header.
            foreach ($s->headers as $c => $h) {
                $ws->setCellValueExplicit([$c + 1, 1], $h, DataType::TYPE_STRING);
            }
            $ws->getStyle("A1:{$lastcol}1")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::C_HEADER]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            ]);
            $ws->getRowDimension(1)->setRowHeight(32);

            // Data.
            $rowgroups = $s->row_groups();
            foreach ($s->rows as $r => $row) {
                $excelrow = $r + 2;
                foreach ($row as $c => $v) {
                    if (
                        $s->kind === sheet::KIND_GROUPED && $c === 0 && isset($rowgroups[$r])
                            && $s->groups[$rowgroups[$r]][1] !== $r
                    ) {
                        continue; // Only the first cell of a merged range holds the value.
                    }
                    if (is_int($v) || is_float($v)) {
                        $ws->setCellValueExplicit([$c + 1, $excelrow], $v, DataType::TYPE_NUMERIC);
                    } else {
                        $ws->setCellValueExplicit([$c + 1, $excelrow], (string)$v, DataType::TYPE_STRING);
                    }
                }
            }
            $lastrow = count($s->rows) + 1;
            if ($s->rows) {
                $ws->getStyle("A2:{$lastcol}{$lastrow}")->applyFromArray([
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                    'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::C_LINE]]],
                ]);
                foreach ($s->rows as $r => $row) {
                    $ws->getStyle("A" . ($r + 2) . ":{$lastcol}" . ($r + 2))->getBorders()->getBottom()
                        ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::C_LINE);
                }
            }
            foreach ($s->centercols as $c) {
                $col = Coordinate::stringFromColumnIndex($c + 1);
                $ws->getStyle("{$col}1:{$col}{$lastrow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
            foreach ($s->pctcols as $c) {
                $col = Coordinate::stringFromColumnIndex($c + 1);
                $ws->getStyle("{$col}2:{$col}{$lastrow}")->getNumberFormat()->setFormatCode('0%');
            }

            if ($s->kind === sheet::KIND_GROUPED) {
                foreach ($s->groups as $g => [$label, $first, $last]) {
                    $r1 = $first + 2;
                    $r2 = $last + 2;
                    if ($g % 2 === 1) {
                        $ws->getStyle("A{$r1}:{$lastcol}{$r2}")->getFill()->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setRGB(self::C_BAND);
                    }
                    if ($r2 > $r1) {
                        $ws->mergeCells("A{$r1}:A{$r2}");
                    }
                    // Excel does not grow merged cells to fit their text, so make small groups tall enough.
                    $lines = (int)ceil(\core_text::strlen($label) / 26);
                    $needed = $lines * 15;
                    $rows = $r2 - $r1 + 1;
                    if ($rows * 15 < $needed) {
                        for ($r = $r1; $r <= $r2; $r++) {
                            $ws->getRowDimension($r)->setRowHeight(ceil($needed / $rows));
                        }
                    }
                    $ws->getStyle("A{$r1}:A{$r2}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => self::C_HEADER], 'size' => 11],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                    ]);
                    $ws->getStyle("A{$r2}:{$lastcol}{$r2}")->getBorders()->getBottom()
                        ->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB(self::C_HEADER);
                }
                $ws->freezePane('B2');
            } else {
                // Zebra rows and filters on plain lists.
                for ($r = 3; $r <= $lastrow; $r += 2) {
                    $ws->getStyle("A{$r}:{$lastcol}{$r}")->getFill()->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB(self::C_BAND);
                }
                $ws->setAutoFilter("A1:{$lastcol}{$lastrow}");
                $ws->freezePane('A2');
            }

            // Column widths from the longest line of each column; long text wraps.
            for ($c = 0; $c < $ncols; $c++) {
                $len = \core_text::strlen($s->headers[$c]) * 0.6;
                foreach ($s->rows as $row) {
                    $v = $row[$c] ?? '';
                    if (is_float($v) || is_int($v)) {
                        $len = max($len, 6);
                        continue;
                    }
                    foreach (explode("\n", (string)$v) as $line) {
                        $len = max($len, \core_text::strlen($line));
                    }
                }
                $max = ($s->kind === sheet::KIND_GROUPED && $c === 0) ? 30 : 60;
                $ws->getColumnDimension(Coordinate::stringFromColumnIndex($c + 1))
                    ->setWidth(max(10, min($max, $len + 2)));
            }

            // Notes under the table.
            $noterow = $lastrow + 2;
            foreach ($s->notes as $note) {
                $ws->setCellValueExplicit([1, $noterow], $note, DataType::TYPE_STRING);
                $ws->getStyle("A{$noterow}")->getFont()->setItalic(true)->setSize(9)->getColor()->setRGB('595959');
                $noterow++;
            }
            // Printing: landscape, one page wide, header row repeated on every page.
            $ws->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
                ->setPaperSize(PageSetup::PAPERSIZE_A4)
                ->setFitToWidth(1)->setFitToHeight(0)
                ->setRowsToRepeatAtTopByStartAndEnd(1, 1);
            $ws->getPageMargins()->setLeft(0.4)->setRight(0.4)->setTop(0.5)->setBottom(0.5);
            $ws->setShowGridlines(false);
            $ws->setSelectedCells('A1');
        }
        if ($book->getSheetCount() === 0) {
            $book->createSheet();
        }
        $book->setActiveSheetIndex(0);
        IOFactory::createWriter($book, $type)->save($path);
    }

    /**
     * CSV: one file when there is one sheet, otherwise a ZIP with one CSV per sheet.
     * Merged cells are repeated on every row so the data stays filterable.
     *
     * @param sheet[] $sheets
     * @param string $dir
     * @param string $basename
     * @return array [path, filename]
     */
    protected static function write_csv(array $sheets, string $dir, string $basename): array {
        $files = [];
        $used = [];
        foreach ($sheets as $s) {
            $name = self::sheet_name($s->name, $used);
            $path = $dir . '/' . clean_filename($basename . ' - ' . $name) . '.csv';
            $fh = fopen($path, 'w');
            fwrite($fh, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows accents correctly.
            fputcsv($fh, $s->headers, ';');
            foreach ($s->rows as $row) {
                foreach ($s->pctcols as $c) {
                    $row[$c] = round($row[$c] * 100) . '%';
                }
                foreach ($row as $c => $v) {
                    if (is_string($v)) {
                        $row[$c] = str_replace("\n", '; ', $v);
                    }
                }
                fputcsv($fh, $row, ';');
            }
            fclose($fh);
            $files[basename($path)] = $path;
        }
        if (count($files) === 1) {
            $path = reset($files);
            return [$path, basename($path)];
        }
        $zippath = $dir . '/' . $basename . '.zip';
        get_file_packer('application/zip')->archive_to_pathname($files, $zippath);
        return [$zippath, $basename . '.zip'];
    }

    /**
     * PDF in landscape. Grouped sheets become one titled table per group, so long groups split cleanly across pages.
     *
     * @param sheet[] $sheets
     * @param string $path
     * @param string $title
     */
    protected static function write_pdf(array $sheets, string $path, string $title): void {
        global $CFG;
        require_once($CFG->libdir . '/pdflib.php');

        $pdf = new \pdf('L', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetTitle($title);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 12);
        $pdf->SetFont('freesans', '', 8);

        $th = 'background-color:#' . self::C_HEADER . ';color:#FFFFFF;font-weight:bold;';
        $esc = function ($v) {
            if ($v === builder::TICK) {
                // FreeSans (default) has no tick glyph; FreeSerif, bundled with Moodle, does.
                return '<span style="font-family:freeserif;color:#1F3864;">' . builder::TICK . '</span>';
            }
            return nl2br(s(is_float($v) ? $v : (string)$v), false);
        };

        foreach ($sheets as $s) {
            $pdf->AddPage();
            $pdf->SetFont('freesans', 'B', 13);
            $pdf->SetTextColor(31, 56, 100);
            $pdf->Cell(0, 8, $title . ' — ' . $s->name, 0, 1);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('freesans', '', 8);

            $grouped = $s->kind === sheet::KIND_GROUPED;
            $headers = $grouped ? array_slice($s->headers, 1) : $s->headers;
            $center = array_map(function ($c) use ($grouped) {
                return $grouped ? $c - 1 : $c;
            }, $s->centercols);
            $pct = array_map(function ($c) use ($grouped) {
                return $grouped ? $c - 1 : $c;
            }, $s->pctcols);

            $tablehead = '<thead><tr>';
            foreach ($headers as $h) {
                $tablehead .= '<th style="' . $th . '">' . s($h) . '</th>';
            }
            $tablehead .= '</tr></thead>';

            $renderrows = function (array $rows) use ($grouped, $center, $pct, $esc) {
                $html = '';
                foreach ($rows as $i => $row) {
                    if ($grouped) {
                        array_shift($row);
                    }
                    $bg = $i % 2 ? 'background-color:#' . self::C_BAND . ';' : '';
                    $html .= '<tr nobr="true">';
                    foreach ($row as $c => $v) {
                        if (in_array($c, $pct)) {
                            $v = round($v * 100) . '%';
                        }
                        $align = in_array($c, $center) ? 'text-align:center;' : '';
                        $html .= '<td style="' . $bg . $align . 'border-bottom:0.2px solid #' . self::C_LINE . ';">'
                            . $esc($v) . '</td>';
                    }
                    $html .= '</tr>';
                }
                return $html;
            };

            if ($grouped) {
                foreach ($s->groups as [$label, $first, $last]) {
                    $rows = array_slice($s->rows, $first, $last - $first + 1);
                    $count = get_string('note_count', 'local_advancedexport', count($rows));
                    $html = '<h3 style="color:#' . self::C_HEADER . ';">' . s($label)
                        . ' <span style="font-size:8pt;color:#595959;font-weight:normal;">(' . s($count) . ')</span></h3>'
                        . '<table cellpadding="3" border="0">' . $tablehead . '<tbody>' . $renderrows($rows)
                        . '</tbody></table><br>';
                    $pdf->writeHTML($html, true, false, true, false, '');
                }
            } else {
                $html = '<table cellpadding="3" border="0">' . $tablehead . '<tbody>' . $renderrows($s->rows)
                    . '</tbody></table>';
                $pdf->writeHTML($html, true, false, true, false, '');
            }
            if ($s->notes) {
                $pdf->SetFont('freesans', 'I', 8);
                foreach ($s->notes as $note) {
                    $pdf->MultiCell(0, 5, $note, 0, 'L');
                }
                $pdf->SetFont('freesans', '', 8);
            }
        }
        $pdf->Output($path, 'F');
    }
}
