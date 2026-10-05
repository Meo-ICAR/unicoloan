<?php

namespace App\Filament\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class M510SediSheet implements FromArray, WithEvents, WithTitle
{
    protected array $dati;

    public function __construct(array $dati)
    {
        $this->dati = $dati;
    }

    public function array(): array
    {
        $rows = [];

        // --- INTESTAZIONE SUPERIORE MAESTRA (RIGA 1) ---
        $rows[] = ['5 di 5_ELENCO SEDI TERRITORIALI', '', '', '', '', '', '', '', ''];

        // --- INTESTAZIONE COLONNE SPECIFICHE DA MOCKUP (RIGA 2) ---
        $rows[] = [
            'Numero iscrizione (M510)',
            'INDIRIZZO',
            'NUMERO CIVICO',
            "CITTA'",
            'CAP',
            'PROVINCIA',
            'REGIONE',
            'RESPONSABILE',
            'SEDE PRINCIPALE (SI/NO)',
        ];

        // --- MAPPARE I RECORD DALLA TABELLA `branches` ---
        $filiali = $this->dati['branches_lista'] ?? [];
        $counter = 1;

        foreach ($filiali as $branch) {
            $rows[] = [
                'SMC'.$counter,  // Codice progressivo Ministeriale
                $branch['address'] ?? '',  // Solo via/piazza senza aggregazioni
                $branch['street_number'] ?? '',  // Numero civico separato
                $branch['city'] ?? '',  // Città
                $branch['zip_code'] ?? '',  // CAP
                $branch['province'] ?? '',  // Provincia
                $branch['region'] ?? '',  // Regione
                $branch['responsabile'] ?? '',  // Cognome Nome o Viceversa
                $branch['is_main_office'] ?? 'NO',  // SI o NO stringente
            ];
            $counter++;
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Sedi Territoriali';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();

                // Unione del titolo principale sulle 9 colonne esatte della tabella
                $sheet->mergeCells('A1:I1');

                // Stile dell'Insegna Superiore (Teal scuro Ministeriale)
                $sheet->getStyle('A1:I1')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 12],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF008B8B']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);

                // Stile delle Colonne Intestazione (Riga 2)
                $sheet->getStyle('A2:I2')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 10, 'color' => ['argb' => 'FF000000']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF48D1CC']],  // Sfondo verde acqua chiaro
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFFFFFFF']]],
                ]);

                // Righe dati (Dalla riga 3 alla fine)
                if ($highestRow >= 3) {
                    // Stile Generale Griglia
                    $sheet->getStyle('A3:I'.$highestRow)->applyFromArray([
                        'font' => ['size' => 10],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE0E0E0']]],
                    ]);

                    // Codici SMC colonna A (Verde acqua come da mockup grafico)
                    $sheet->getStyle('A3:A'.$highestRow)->applyFromArray([
                        'font' => ['bold' => true],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE0F7F6']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);

                    // Allineamenti centrati per CAP, Civico, Province e Flag SI/NO
                    $sheet->getStyle('C3:G'.$highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle('I3:I'.$highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                // Larghezze campionate per evitare troncamenti di dati
                $sheet->getColumnDimension('A')->setWidth(18);  // Numero iscrizione
                $sheet->getColumnDimension('B')->setWidth(28);  // Indirizzo
                $sheet->getColumnDimension('C')->setWidth(16);  // Civico
                $sheet->getColumnDimension('D')->setWidth(16);  // Citta
                $sheet->getColumnDimension('E')->setWidth(12);  // CAP
                $sheet->getColumnDimension('F')->setWidth(20);  // Provincia
                $sheet->getColumnDimension('G')->setWidth(18);  // Regione
                $sheet->getColumnDimension('H')->setWidth(26);  // Responsabile
                $sheet->getColumnDimension('I')->setWidth(24);  // Sede principale

                // Altezza righe per dare respiro alla tabella
                $sheet->getRowDimension(1)->setRowHeight(35);
                $sheet->getRowDimension(2)->setRowHeight(40);
            },
        ];
    }
}
