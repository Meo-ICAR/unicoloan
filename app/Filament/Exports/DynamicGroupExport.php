<?php

namespace App\Filament\Exports;

use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class DynamicGroupExport extends ExcelExport implements WithEvents
{
    protected ?string $groupBy = null;
    protected array $sumColumns = [];

    const FORMAT_CURRENCY = '#,##0.00" €"';

    public static function make(?string $name = null): static
    {
        return parent::make($name ?? 'export')
            ->fromTable()
            ->withFilename('report_' . now()->format('Y-m-d_H-i'));
    }

    public function groupBy(string $column): static
    {
        $this->groupBy = $column;
        return $this;
    }

    public function sumColumns(array $columns): static
    {
        $this->sumColumns = $columns;
        return $this;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $this->processSheet($event);
            }
        ];
    }

    protected function processSheet(AfterSheet $event): void
    {
        $sheet = $event->sheet->getDelegate();

        // 1. GESTIONE FILTRI IN CIMA
        $livewire = $this->getLivewire();
        $appliedFilters = $livewire->tableFilters ?? [];

        $filterStrings = [];
        foreach ($appliedFilters as $name => $data) {
            if (!empty($data['value'])) {
                $val = is_array($data['value']) ? implode(', ', $data['value']) : $data['value'];
                $filterStrings[] = strtoupper($name) . ': ' . $val;
            }
        }
        $filtersText = 'FILTRI APPLICATI: ' . (empty($filterStrings) ? 'Nessuno' : implode(' | ', $filterStrings));

        // Inserimento spazio per i filtri
        $sheet->insertNewRowBefore(1, 2);
        $sheet->setCellValue('A1', $filtersText);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setItalic(true)->getColor()->setARGB('FF555555');

        // 2. FORMATTAZIONE INTESTAZIONI (RIGA 3) IN GRASSETTO
        $headerRow = 3;
        $highestColumn = $sheet->getHighestColumn();
        $sheet->getStyle("A{$headerRow}:{$highestColumn}{$headerRow}")->getFont()->setBold(true);

        // Identificazione colonne da sommare e dati
        $highestRow = $sheet->getHighestRow();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

        if ($highestRow <= $headerRow)
            return;

        $headings = $sheet->rangeToArray("A{$headerRow}:{$highestColumn}{$headerRow}", null, true, false)[0];
        $headingsLower = array_map(fn($h) => strtolower(trim((string) $h)), $headings);

        $sumIndices = [];
        foreach ($this->sumColumns as $col) {
            $idx = array_search(strtolower($col), $headingsLower);
            if ($idx !== false)
                $sumIndices[] = $idx;
        }

        $dataStartRow = $headerRow + 1;
        $rows = $sheet->rangeToArray("A{$dataStartRow}:{$highestColumn}{$highestRow}", null, true, false);

        // --- LOGICA A: CON RAGGRUPPAMENTO ---
        if ($this->groupBy) {
            $groupByIndex = array_search(strtolower($this->groupBy), $headingsLower);

            if ($groupByIndex !== false) {
                $groups = [];
                foreach ($rows as $row) {
                    $groupValue = $row[$groupByIndex] ?? '';
                    $groups[$groupValue][] = $row;
                }

                $sheet->removeRow($dataStartRow, $highestRow - $headerRow);

                $grandTotals = array_fill_keys($sumIndices, 0);
                $currentRow = $dataStartRow;

                foreach ($groups as $groupName => $groupRows) {
                    $groupSums = array_fill_keys($sumIndices, 0);

                    foreach ($groupRows as $row) {
                        foreach ($row as $colIdx => $value) {
                            $colLetter = Coordinate::stringFromColumnIndex($colIdx + 1);
                            $sheet->setCellValue($colLetter . $currentRow, $value);
                        }
                        foreach ($sumIndices as $idx) {
                            $val = $this->parseNumericValue($row[$idx] ?? 0);
                            $groupSums[$idx] += $val;
                        }
                        $currentRow++;
                    }

                    // Riga Totale Gruppo
                    foreach ($sumIndices as $idx)
                        $grandTotals[$idx] += $groupSums[$idx];

                    $this->writeTotalRow($sheet, $currentRow, $groupByIndex, 'TOTALE ' . strtoupper((string) $groupName), $groupSums, $sumIndices, $highestColumnIndex, false);
                    $currentRow += 2;
                }

                // Gran Totale Finale (dopo i gruppi)
                $this->writeTotalRow($sheet, $currentRow, $groupByIndex, 'GRAN TOTALE COMPLESSIVO', $grandTotals, $sumIndices, $highestColumnIndex, true);
                return;
            }
        }

        // --- LOGICA B: SENZA RAGGRUPPAMENTO (SOLO GRAN TOTALE) ---
        $grandTotals = array_fill_keys($sumIndices, 0);
        foreach ($rows as $row) {
            foreach ($sumIndices as $idx) {
                $grandTotals[$idx] += $this->parseNumericValue($row[$idx] ?? 0);
            }
        }

        // Scriviamo il gran totale in fondo ai dati esistenti
        $lastDataRow = $highestRow + 1;
        $this->writeTotalRow($sheet, $lastDataRow, 0, 'GRAN TOTALE COMPLESSIVO', $grandTotals, $sumIndices, $highestColumnIndex, true);
    }

    // Helper per pulire i valori numerici
    protected function parseNumericValue($val): float
    {
        if (is_string($val)) {
            $val = str_replace(['€', '.', ' '], '', $val);
            $val = str_replace(',', '.', $val);
        }
        return (float) $val;
    }

    // Helper per scrivere una riga di totale (subtotale o gran totale)
    protected function writeTotalRow($sheet, $rowIdx, $labelColIdx, $label, $totals, $sumIndices, $maxCol, $isGrandTotal)
    {
        $colLetterLabel = Coordinate::stringFromColumnIndex($labelColIdx + 1);
        $sheet->setCellValue($colLetterLabel . $rowIdx, $label);

        $rowRange = Coordinate::stringFromColumnIndex(1) . $rowIdx . ':' . Coordinate::stringFromColumnIndex($maxCol) . $rowIdx;
        $style = $sheet->getStyle($rowRange);
        $style->getFont()->setBold(true);

        if ($isGrandTotal) {
            $style->getFont()->setSize(12);
            $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD9D9D9');
        }

        foreach ($sumIndices as $idx) {
            $colLetter = Coordinate::stringFromColumnIndex($idx + 1);
            $cell = $colLetter . $rowIdx;
            $sheet->setCellValue($cell, $totals[$idx]);
            $sheet->getStyle($cell)->getNumberFormat()->setFormatCode(self::FORMAT_CURRENCY);
        }
    }
}
