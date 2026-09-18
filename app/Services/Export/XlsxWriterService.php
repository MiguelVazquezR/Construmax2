<?php

namespace App\Services\Export;

use RuntimeException;
use ZipArchive;

/**
 * Minimal XLSX writer built on PHP's zip extension (no external dependencies).
 *
 * It produces a single-sheet workbook with inline strings, a bold header row,
 * an optional bold footer, optional column widths and the built-in
 * "#,##0.00" number format for float values.
 */
class XlsxWriterService
{
    /**
     * @param string $sheetTitle  Worksheet tab name (truncated to 31 chars).
     * @param string[] $headers   Column labels, rendered as the bold first row.
     * @param array<int, array<int, string|int|float|null>> $rows Data rows.
     * @param array<int, int|float> $columnWidths Column widths indexed by position (0-based).
     * @param array<int, string|int|float|null>|null $footer Optional bold closing row.
     *
     * @return string Absolute path of the generated temporary file.
     */
    public function generate(
        string $sheetTitle,
        array $headers,
        array $rows,
        array $columnWidths = [],
        ?array $footer = null,
    ): string {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');

        if ($path === false) {
            throw new RuntimeException('Unable to create a temporary file for the export.');
        }

        $zip = new ZipArchive();

        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to open the XLSX file.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rootRelations());
        $zip->addFromString('xl/workbook.xml', $this->workbook($sheetTitle));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelations());
        $zip->addFromString('xl/styles.xml', $this->styles());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheet($headers, $rows, $columnWidths, $footer));

        $zip->close();

        return $path;
    }

    private function sheet(array $headers, array $rows, array $columnWidths, ?array $footer): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';

        if ($columnWidths !== []) {
            $xml .= '<cols>';

            foreach ($columnWidths as $index => $width) {
                $xml .= sprintf(
                    '<col min="%1$d" max="%1$d" width="%2$s" customWidth="1"/>',
                    $index + 1,
                    $width
                );
            }

            $xml .= '</cols>';
        }

        $xml .= '<sheetData>';
        $xml .= $this->row(1, $headers, true);

        $rowNumber = 2;

        foreach ($rows as $row) {
            $xml .= $this->row($rowNumber++, $row);
        }

        if ($footer !== null) {
            $xml .= $this->row($rowNumber, $footer, true);
        }

        $xml .= '</sheetData></worksheet>';

        return $xml;
    }

    private function row(int $number, array $values, bool $bold = false): string
    {
        $cells = '';

        foreach (array_values($values) as $index => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $reference = $this->columnLetter($index) . $number;

            if (is_int($value) || is_float($value)) {
                $style = $bold ? 3 : (is_float($value) ? 2 : 0);

                $cells .= sprintf(
                    '<c r="%s"%s><v>%s</v></c>',
                    $reference,
                    $style !== 0 ? ' s="' . $style . '"' : '',
                    $value
                );

                continue;
            }

            $cells .= sprintf(
                '<c r="%s" t="inlineStr"%s><is><t xml:space="preserve">%s</t></is></c>',
                $reference,
                $bold ? ' s="1"' : '',
                $this->escape((string) $value)
            );
        }

        return sprintf('<row r="%d">%s</row>', $number, $cells);
    }

    private function columnLetter(int $index): string
    {
        $letter = '';

        for ($i = $index; $i >= 0; $i = intdiv($i, 26) - 1) {
            $letter = chr(65 + ($i % 26)) . $letter;
        }

        return $letter;
    }

    private function escape(string $value): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value) ?? '';

        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private function rootRelations(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function workbook(string $sheetTitle): string
    {
        $title = $this->escape(mb_substr($sheetTitle, 0, 31));

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . $title . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private function workbookRelations(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2">'
            . '<font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            . '<borders count="1"><border/></borders>'
            . '<cellStyleXfs count="1"><xf/></cellStyleXfs>'
            . '<cellXfs count="4">'
            . '<xf/>'
            . '<xf fontId="1" applyFont="1"/>'
            . '<xf numFmtId="4" applyNumberFormat="1"/>'
            . '<xf fontId="1" numFmtId="4" applyFont="1" applyNumberFormat="1"/>'
            . '</cellXfs>'
            . '</styleSheet>';
    }
}
