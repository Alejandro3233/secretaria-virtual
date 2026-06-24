<?php

namespace App\Services;

class ExcelWorkbookService
{
    /**
     * @param  array<int, array{name: string, rows: array<int, array<int, mixed>>}>  $sheets
     */
    public function create(array $sheets): string
    {
        $files = [
            '[Content_Types].xml' => $this->contentTypes(count($sheets)),
            '_rels/.rels' => $this->packageRelationships(),
            'docProps/app.xml' => $this->appProperties($sheets),
            'docProps/core.xml' => $this->coreProperties(),
            'xl/workbook.xml' => $this->workbook($sheets),
            'xl/_rels/workbook.xml.rels' => $this->workbookRelationships(count($sheets)),
            'xl/styles.xml' => $this->styles(),
        ];

        foreach ($sheets as $index => $sheet) {
            $files['xl/worksheets/sheet'.($index + 1).'.xml'] = $this->worksheet($sheet['rows']);
        }

        return $this->zip($files);
    }

    private function contentTypes(int $sheetCount): string
    {
        $worksheets = '';
        for ($index = 1; $index <= $sheetCount; $index++) {
            $worksheets .= '<Override PartName="/xl/worksheets/sheet'.$index.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            .'<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            .$worksheets.'</Types>';
    }

    private function packageRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            .'</Relationships>';
    }

    /** @param array<int, array{name: string, rows: array<int, array<int, mixed>>}> $sheets */
    private function appProperties(array $sheets): string
    {
        $names = implode('', array_map(fn (array $sheet): string => '<vt:lpstr>'.$this->xml($sheet['name']).'</vt:lpstr>', $sheets));

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            .'<Application>Secretary365</Application><TitlesOfParts><vt:vector size="'.count($sheets).'" baseType="lpstr">'.$names.'</vt:vector></TitlesOfParts>'
            .'</Properties>';
    }

    private function coreProperties(): string
    {
        $createdAt = now()->utc()->format('Y-m-d\TH:i:s\Z');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            .'<dc:creator>Secretary365</dc:creator><dc:title>Informe de actividad</dc:title><dcterms:created xsi:type="dcterms:W3CDTF">'.$createdAt.'</dcterms:created>'
            .'</cp:coreProperties>';
    }

    /** @param array<int, array{name: string, rows: array<int, array<int, mixed>>}> $sheets */
    private function workbook(array $sheets): string
    {
        $items = '';
        foreach ($sheets as $index => $sheet) {
            $items .= '<sheet name="'.$this->xml($sheet['name']).'" sheetId="'.($index + 1).'" r:id="rId'.($index + 1).'"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$items.'</sheets></workbook>';
    }

    private function workbookRelationships(int $sheetCount): string
    {
        $items = '';
        for ($index = 1; $index <= $sheetCount; $index++) {
            $items .= '<Relationship Id="rId'.$index.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$index.'.xml"/>';
        }
        $items .= '<Relationship Id="rId'.($sheetCount + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$items.'</Relationships>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFC91F5D"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs>'
            .'</styleSheet>';
    }

    /** @param array<int, array<int, mixed>> $rows */
    private function worksheet(array $rows): string
    {
        $columnCount = max(array_map('count', $rows) ?: [1]);
        $widths = array_fill(0, $columnCount, 10);
        $rowXml = '';

        foreach ($rows as $rowIndex => $row) {
            $cells = '';
            foreach (array_values($row) as $columnIndex => $value) {
                $text = $value === null ? '' : (string) $value;
                $widths[$columnIndex] = min(45, max($widths[$columnIndex], mb_strlen($text) + 2));
                $reference = $this->columnName($columnIndex + 1).($rowIndex + 1);
                $style = $rowIndex === 0 ? ' s="1"' : '';
                $cells .= '<c r="'.$reference.'" t="inlineStr"'.$style.'><is><t xml:space="preserve">'.$this->xml($text).'</t></is></c>';
            }
            $rowXml .= '<row r="'.($rowIndex + 1).'">'.$cells.'</row>';
        }

        $columns = '';
        foreach ($widths as $index => $width) {
            $columns .= '<col min="'.($index + 1).'" max="'.($index + 1).'" width="'.$width.'" customWidth="1"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<cols>'.$columns.'</cols><sheetData>'.$rowXml.'</sheetData><autoFilter ref="A1:'.$this->columnName($columnCount).'1"/>'
            .'</worksheet>';
    }

    private function columnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)).$name;
            $number = intdiv($number, 26);
        }

        return $name;
    }

    private function xml(string $value): string
    {
        $value = preg_replace('/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $value) ?? '';

        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /** @param array<string, string> $files */
    private function zip(array $files): string
    {
        $archive = '';
        $directory = '';
        $offset = 0;
        [$dosTime, $dosDate] = $this->dosDateTime();

        foreach ($files as $name => $contents) {
            $crc = crc32($contents);
            $size = strlen($contents);
            $nameLength = strlen($name);
            $archive .= pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, $dosTime, $dosDate, $crc, $size, $size, $nameLength, 0).$name.$contents;
            $directory .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, $dosTime, $dosDate, $crc, $size, $size, $nameLength, 0, 0, 0, 0, 0, $offset).$name;
            $offset = strlen($archive);
        }

        return $archive.$directory.pack('VvvvvVVv', 0x06054b50, 0, 0, count($files), count($files), strlen($directory), strlen($archive), 0);
    }

    /** @return array{int, int} */
    private function dosDateTime(): array
    {
        $date = now();

        return [
            ($date->hour << 11) | ($date->minute << 5) | intdiv($date->second, 2),
            (($date->year - 1980) << 9) | ($date->month << 5) | $date->day,
        ];
    }
}
