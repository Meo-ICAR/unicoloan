<?php

namespace App\Filament\Exports\Sheets;

use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class M510AnagraficaSheet implements WithEvents, WithTitle
{
    protected array $dati;

    public function __construct(array $dati)
    {
        $this->dati = $dati;
    }

    public function title(): string
    {
        return 'Anagrafica';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Imposta larghezza colonne di base
                $sheet->getColumnDimension('A')->setWidth(8);
                $sheet->getColumnDimension('B')->setWidth(50);
                $sheet->getColumnDimension('C')->setWidth(30);
                $sheet->getColumnDimension('D')->setWidth(30);

                // --- INTESTAZIONE (RIGA 1) ---
                $sheet->mergeCells('A1:B1');
                $sheet->setCellValue('A1', 'ANAGRAFICA');
                $sheet->mergeCells('C1:D1');
                $sheet->setCellValue('C1', 'Numero iscrizione (M510)');

                // Stili riga 1
                $sheet->getStyle('A1:B1')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => '008080']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
                ]);
                $sheet->getStyle('C1:D1')->applyFromArray([
                    'font' => ['bold' => true, 'italic' => true, 'underlined' => true, 'color' => ['argb' => 'FFCC0000']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF2F2F2']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
                ]);

                // --- MAPPARE I CAMPI DEL DATABASE DEL TUO ARRAY ---
                $righeForm = [
                    2 => ['MA1', 'DENOMINAZIONE SOCIALE / RAGIONE SOCIALE', $this->dati['ragione_sociale']],
                    3 => ['MA2', 'C.F. / P.IVA', $this->dati['piva']],
                    4 => ['MA3', 'PERIODO DI RILEVAZIONE', $this->dati['periodo']],
                ];

                foreach ($righeForm as $riga => $info) {
                    $sheet->setCellValue("A{$riga}", $info[0]);
                    $sheet->setCellValue("B{$riga}", $info[1]);
                    $sheet->mergeCells("C{$riga}:D{$riga}");
                    $sheet->setCellValue("C{$riga}", $info[2]);

                    // Stile standard celle verdi/grigie
                    $this->applicaStileRigaForm($sheet, $riga);
                }

                // --- GESTIONE MA4 (RIGA SDOPPIATA) ---
                $sheet->mergeCells('A5:A6');
                $sheet->setCellValue('A5', 'MA4');
                $sheet->mergeCells('B5:B6');
                $sheet->setCellValue('B5', "N. DIPENDENTI E COLLABORATORI INDICATI\nALL'ORGANISMO");
                $sheet->getStyle('B5')->getAlignment()->setWrapText(true);

                $sheet->setCellValue('C5', 'MA4A - Numero dipendenti');
                $sheet->setCellValue('D5', 'MA4B - Numero collaboratori');
                $sheet->setCellValue('C6', $this->dati['num_dipendenti']);
                $sheet->setCellValue('D6', $this->dati['num_collaboratori']);

                // Applica stili a MA4
                $sheet->getStyle('A5:B6')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('66CDAA');
                $sheet->getStyle('C5:D6')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF2F2F2');
                $sheet->getStyle('A5:D6')->getFont()->setBold(true);

                // --- RESTO DEL FORM (MA5 - MA8) ---
                $righeFormSotto = [
                    7 => ['MA5', 'N. SEDI TERRITORIALI', $this->dati['sedi_territoriali']],
                    8 => ['MA6', 'Numero progressivo della segnalazione (N°/Anno)', $this->dati['progressivo']],
                    9 => ['MA7', 'Compilazione Profilo Economico Operativo ANALITICO', $this->dati['profilo_analitico']],
                    10 => ['MA8', 'Compilazione Profilo Economico Operativo BASE', $this->dati['profilo_base']],
                ];

                foreach ($righeFormSotto as $riga => $info) {
                    $sheet->setCellValue("A{$riga}", $info[0]);
                    $sheet->setCellValue("B{$riga}", $info[1]);
                    $sheet->mergeCells("C{$riga}:D{$riga}");
                    $sheet->setCellValue("C{$riga}", $info[2]);
                    $this->applicaStileRigaForm($sheet, $riga);
                }
            }
        ];
    }

    private function applicaStileRigaForm($sheet, $riga)
    {
        $sheet->getStyle("A{$riga}:B{$riga}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('66CDAA');
        $sheet->getStyle("C{$riga}:D{$riga}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF2F2F2');
        $sheet->getStyle("A{$riga}:D{$riga}")->getFont()->setBold(true);
    }
}
