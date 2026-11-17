<?php

namespace App\Services\Import;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Minimal SpreadsheetML reader for the single-sheet workbooks in this project's
 * import format. The spreadsheet rows are already header-addressed, so the
 * reader resolves each cell through its own reference instead of assuming a
 * contiguous column order.
 */
class ExcelReader
{
    /**
     * @return array<string, int> sheet name to row count
     */
    public function sheetNames(string $path): array
    {
        $zip = $this->open($path);

        try {
            $workbook = new SimpleXMLElement((string) $zip->getFromName('xl/workbook.xml'));

            $sheets = [];

            foreach ($workbook->sheets->sheet as $sheet) {
                $name = (string) $sheet['name'];
                $sheets[$name] = 0;

                foreach ($this->readSheet($zip, $this->sheetPath((string) $sheet['sheetId'])) as $ignored) {
                    $sheets[$name]++;
                }
            }

            return $sheets;
        } finally {
            $zip->close();
        }
    }

    /**
     * @return array<int, array<string, string>> one associative row per record
     */
    public function read(string $path, ?string $sheetName = null): array
    {
        $zip = $this->open($path);

        try {
            [, $firstSheetPath] = $this->locateSheet($zip, $sheetName);
            $rows = iterator_to_array($this->readSheet($zip, $firstSheetPath), false);

            if ($rows === []) {
                return [];
            }

            // The first row holds the column labels; the keys are the column
            // references the sheet addresses each cell by.
            $columns = array_keys($rows[0]);
            $header = array_map(
                fn (string $column): string => trim((string) ($rows[0][$column] ?? '')),
                $columns,
            );

            $records = [];

            foreach (array_slice($rows, 1) as $row) {
                $record = [];

                foreach ($header as $position => $label) {
                    if ($label === '') {
                        continue;
                    }

                    $record[$label] = trim((string) ($row[$columns[$position]] ?? ''));
                }

                if (array_filter($record, fn (string $value): bool => $value !== '') !== []) {
                    $records[] = $record;
                }
            }

            return $records;
        } finally {
            $zip->close();
        }
    }

    /**
     * @return \Generator<int, array<string, string>>
     */
    private function readSheet(ZipArchive $zip, string $path): \Generator
    {
        $sharedStrings = $this->sharedStrings($zip);
        $worksheet = new SimpleXMLElement((string) $zip->getFromName($path));

        foreach ($worksheet->sheetData->row as $row) {
            $cells = [];

            foreach ($row->c as $cell) {
                $reference = (string) $cell['r'];
                $column = preg_replace('/\d+/', '', $reference) ?? $reference;
                $cells[$column] = $this->cellValue($cell, $sharedStrings);
            }

            ksort($cells, SORT_NATURAL);

            yield $cells;
        }
    }

    /**
     * @param  array<int, string>  $sharedStrings
     */
    private function cellValue(SimpleXMLElement $cell, array $sharedStrings): string
    {
        $type = isset($cell['t']) ? (string) $cell['t'] : 'n';

        if ($type === 'inlineStr') {
            return (string) $cell->is->t;
        }

        $value = isset($cell->v) ? (string) $cell->v : '';

        if ($type !== 's') {
            return $value;
        }

        return $sharedStrings[(int) $value] ?? '';
    }

    /**
     * @return array<int, string>
     */
    private function sharedStrings(ZipArchive $zip): array
    {
        $contents = $zip->getFromName('xl/sharedStrings.xml');

        if ($contents === false) {
            return [];
        }

        $strings = [];

        foreach ((new SimpleXMLElement($contents))->si as $entry) {
            $text = isset($entry->t) ? (string) $entry->t : '';

            if (isset($entry->r)) {
                foreach ($entry->r as $run) {
                    $text .= (string) $run->t;
                }
            }

            $strings[] = $text;
        }

        return $strings;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function locateSheet(ZipArchive $zip, ?string $sheetName): array
    {
        $workbook = new SimpleXMLElement((string) $zip->getFromName('xl/workbook.xml'));

        foreach ($workbook->sheets->sheet as $sheet) {
            $name = (string) $sheet['name'];

            if ($sheetName === null || $name === $sheetName) {
                return [$name, $this->sheetPath((string) $sheet['sheetId'])];
            }
        }

        throw new RuntimeException("Sheet [{$sheetName}] does not exist in the workbook.");
    }

    private function sheetPath(string $sheetId): string
    {
        return 'xl/worksheets/sheet'.$sheetId.'.xml';
    }

    private function open(string $path): ZipArchive
    {
        if (! is_readable($path)) {
            throw new RuntimeException("Spreadsheet [{$path}] is not readable.");
        }

        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException("Spreadsheet [{$path}] is not a valid xlsx archive.");
        }

        return $zip;
    }
}
