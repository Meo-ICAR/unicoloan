<?php

namespace App\Filament\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class M510PrudenzialeSheet implements FromArray, WithTitle, WithEvents
{
    protected array $dati;

    public function __construct(array $dati)
    {
        $this->dati = $dati;
    }

    public function array(): array
    {
        $rows = [];

        // --- SEZIONE ALTA: KPI E DATI AGGREGATI ---
        $rows[] = ['3 di 5 _ PROFILO PRUDENZIALE E DI CONTROLLO', '', '', '', '', ''];
        $rows[] = ['GRUPPO DI APPARTENENZA', $this->dati['gruppo'] ?? '', '', '', '', ''];
        $rows[] = ['VOLUME PROVVIGIONI PERCEPITE', $this->dati['provvigioni'] ?? '0.00', '', '', '', ''];
        $rows[] = ['NUMERO RECLAMI RICEVUTI', $this->dati['reclami'] ?? 0, '', '', '', ''];
        $rows[] = ['NUMERO SEGNALAZIONI OPERAZIONI SOSPETTE (SOS)', $this->dati['sos'] ?? 0, '', '', '', ''];
        $rows[] = ['ISPEZIONI AUTORITÀ (PROGRAMMATE / EFFETTUATE)', ($this->dati['ispezioni_prog'] ?? 0) . ' / ' . ($this->dati['ispezioni_eff'] ?? 0), '', '', '', ''];
        $rows[] = ['INTERNAL AUDIT (PROGRAMMATI / EFFETTUATI)', ($this->dati['audit_prog'] ?? 0) . ' / ' . ($this->dati['audit_eff'] ?? 0), '', '', '', ''];

        // Spazio di separazione
        $rows[] = ['', '', '', '', '', ''];

        // --- SEZIONE BASSA: TABELLA REGISTRO CONTROLLI (MPP9) ---
        $rows[] = ['REGISTRO DEI RILIEVI RISCONTRATI (MPP9)', '', '', '', '', ''];

        // Intestazione tabella dei rilievi
        $rows[] = [
            "SOGGETTO SOTTOPOSTO A CONTROLLO\n(Collaboratore / Dipendente / Fornitore)",
            'DATA CONTROLLO',
            'AUDITOR / ISPETTORE',
            'RILIEVO RISCONTRATO',
            'PIANO DI RIMEDIO RICHIESTO',
            'TERMINE ADEMPIMENTO'
        ];

        // Ciclo sui rilievi caricati dalla tabella `audits`
        $rilievi = $this->dati['rilievi_lista'] ?? [];
        foreach ($rilievi as $audit) {
            $rows[] = [
                $audit['collaboratore'] ?? 'N/D',  // Risolto tramite il polimorfismo auditable
                $audit['executed_at'] ?? '',  // Campo DB: data di esecuzione
                $audit['auditor_name'] ?? '',  // Campo DB: nome auditor
                $audit['summary'] ?? 'Nessun rilievo',  // Campo DB: sintesi risultati/rilievo
                $audit['remediation_plan'] ?? 'N/A',  // Campo DB: piano di rimedio
                $audit['followup_date'] ?? '',  // Campo DB: data verifica rimedi
            ];
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Profilo Prudenziale';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();

                // Unioni celle Intestazioni Macro
                $sheet->mergeCells('A1:F1');
                $sheet->mergeCells('B2:F2');
                $sheet->mergeCells('B3:F3');
                $sheet->mergeCells('B4:F4');
                $sheet->mergeCells('B5:F5');
                $sheet->mergeCells('B6:F6');
                $sheet->mergeCells('B7:F7');
                $sheet->mergeCells('A9:F9');  // Titolo Tabella MPP9

                // Stile Header Principale
                $sheet->getStyle('A1:F1')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF008B8B']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
                ]);

                // Stile Etichette KPI (A2:A7)
                $sheet->getStyle('A2:A7')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 9],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF48D1CC']],
                ]);

                // Stile Titolo Sezione MPP9 (Riga 9)
                $sheet->getStyle('A9:F9')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF20B2AA']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
                ]);

                // Stile Colonne Intestazione Tabella MPP9 (Riga 10)
                $sheet->getStyle('A10:F10')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 9],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF48D1CC']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFFFFFFF']]]
                ]);

                // Formattazione Righe Dati Tabella (Dalla riga 11 in poi)
                if ($highestRow >= 11) {
                    $sheet->getStyle('A11:F' . $highestRow)->applyFromArray([
                        'font' => ['size' => 9],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF2F2F2']]
                    ]);

                    // Centra le date e i nomi auditor
                    $sheet->getStyle('B11:C' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle('F11:F' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                // Larghezza Colonne
                foreach (range('A', 'F') as $col) {
                    $sheet->getColumnDimension($col)->setWidth($col === 'D' || $col === 'E' ? 35 : 22);
                }
            },
        ];
    }
}
