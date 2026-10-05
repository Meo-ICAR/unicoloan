<?php

namespace App\Filament\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class M510EconomicoBaseSheet implements FromArray, WithEvents, WithTitle
{
    protected array $prodotti;

    public function __construct(array $prodotti)
    {
        $this->prodotti = $prodotti;
    }

    public function array(): array
    {
        $rows = [];

        // RIGA 1: Titolo principale
        $rows[] = [
            '2 di 5 _ PROFILO ECONOMICO/OPERATIVO ANALITICO',
            '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '',
        ];

        // RIGA 2: Intestazioni di primo livello
        $rows[] = [
            "Numero\niscrizione\n(M510)",  // A
            'DENOMINAZIONE ISTITUTO EROGANTE',  // B
            'CONVENZIONE SI/NO',  // C
            "MODALITA' GESTIONE\nRICHIESTA DI\nFINANZIAMENTO",  // D
            'PRODOTTI/O CREDITIZI/O OGGETTO DELLA CONVENZIONE / SERVIZIO PRESTATO',  // E
            'PRATICHE',  // F (Unito F2:G2)
            '',          // G
            'EROGATO',   // H (Unito H2:I2)
            '',          // I
            "TOTALE PROVVIGIONI\nRICONOSCIUTE DALLA\nCLIENTELA",  // J
            "TOTALE PROVVIGIONI\nRICONOSCIUTE\nDALL'ISTITUTO EROGANTE\n(PRINCIPIO DI COMPETENZA)",  // K
            "TOTALE PREMI (QUALITATIVI E\nQUANTITATIVI) RICONOSCIUTI\nDALL'ISTITUTO EROGANTE\n(PRINCIPIO DI COMPETENZA)",  // L
            "(PAY-IN)\nPROVVIGIONI ASSICURATIVE MATURATE -\nProduzione assicurativa - creditizia\n\n(PRINCIPIO DI COMPETENZA)",  // M (Unito M2:O2)
            '', '',
            "AMMONTARE DELLE\nPROVVIGIONI RICONOSCIUTE\nALLA RETE -\nINTERMEDIAZIONE DEL\nCREDITO\n(PRINCIPIO DI COMPETENZA)",  // P
            "(PAY-OUT)\nAMMONTARE DELLE PROVVIGIONI RICONOSCIUTE ALLA RETE -\nINTERMEDIAZIONE ASSICURATIVA\n\n(PRINCIPIO DI COMPETENZA)",  // Q (Unito Q2:S2)
            '', '',
            "N° RIVALSE AI SENSI DEL'ART.\n125 - SEXIES, DEL TUB",  // T
            "AMMONTARE DELLE\nPROVVIGIONI RETROCESSE AL\nFINANZIATORE IN SEGUITO\nALLA RIVALSA",  // U
        ];

        // RIGA 3: Intestazioni di secondo livello
        $rows[] = [
            '', '', '', '', '', // A, B, C, D, E vertical merged
            "N° Pratiche intermediate per\nprodotto/servizio",  // F
            "N° Pratiche di\nfinanziamento in\nlavorazione",  // G
            "Montante lordo / Importo\nerogato per prodotto",  // H
            "Valore delle pratiche di\nfinanziamento in\nlavorazione",  // I
            '', '', '', // J, K, L vertical merged
            "da banche/Intermediari\nfinanziari",  // M
            'da Broker',  // N
            'da Broker Captive',  // O
            '', // P vertical merged
            "da banche/Intermediari\nfinanziari",  // Q
            'da Broker',  // R
            'da Broker Captive',  // S
            '', '', // T, U vertical merged
        ];

        // RIGHE DATI: mappate sul nuovo ordine delle colonne
        foreach ($this->prodotti as $index => $row) {
            $numeroMPEB = 'MPEB'.($index + 1);

            $rows[] = [
                $numeroMPEB,  // A
                $row['abi_name'] ?? '',  // B
                ($row['is_convenzione'] ?? false) ? 'SI' : 'NO', // C
                $row['gestione'] ?? '',  // D
                $row['prodotto_creditizio'] ?? '',  // E
                $row['pratiche_intermediate'] ?? 0,  // F
                $row['pratiche_lavorazione'] ?? 0,  // G
                $row['erogato_lordo'] ?? '0.00',  // H
                $row['erogato_lavorazione'] ?? '0.00',  // I
                $row['provv_clientela'] ?? '0.00',  // J
                $row['provv_istituto_comp'] ?? '0.00',  // K
                $row['premi_istituto_comp'] ?? '0.00',  // L
                $row['payin_ass_banche'] ?? '0.00',  // M
                $row['payin_ass_broker'] ?? '0.00',  // N
                $row['payin_ass_broker_cap'] ?? '0.00',  // O
                $row['payout_rete_credito'] ?? '0.00',  // P
                $row['payout_rete_ass_banche'] ?? '0.00',  // Q
                $row['payout_rete_ass_broker'] ?? '0.00',  // R
                $row['payout_rete_ass_broker_cap'] ?? '0.00',  // S
                $row['num_rivalse'] ?? 0,  // T
                $row['importo_retrocesse'] ?? '0.00',  // U
            ];
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Profilo Economico Analitico';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();

                // Merge orizzontali righe 1 e 2
                $sheet->mergeCells('A1:U1');
                $sheet->mergeCells('F2:G2');
                $sheet->mergeCells('H2:I2');
                $sheet->mergeCells('M2:O2'); // PAY-IN (M, N, O)
                $sheet->mergeCells('Q2:S2'); // PAY-OUT (Q, R, S)

                // Merge verticali per le colonne singole (Righe 2 e 3)
                $colonneSingole = ['A', 'B', 'C', 'D', 'E', 'J', 'K', 'L', 'P', 'T', 'U'];
                foreach ($colonneSingole as $col) {
                    $sheet->mergeCells("{$col}2:{$col}3");
                }

                // Stile Titolo Principale (Teal scuro)
                $sheet->getStyle('A1:U1')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 11],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF008080']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);

                // Stile Intestazioni Tabella (Teal / Verde Acqua)
                $sheet->getStyle('A2:U3')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 9, 'color' => ['argb' => 'FF000000']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF48D1CC']],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFFFFFFF']],
                    ],
                ]);

                // Stile Corpo Dati
                if ($highestRow >= 4) {
                    $sheet->getStyle('A4:U'.$highestRow)->applyFromArray([
                        'font' => ['size' => 9],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                        'borders' => [
                            'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE0E0E0']],
                        ],
                    ]);

                    $sheet->getStyle('A4:A'.$highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle('C4:D'.$highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle('F4:U'.$highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                // Impostazione larghezze colonne
                $sheet->getColumnDimension('A')->setWidth(14);
                $sheet->getColumnDimension('B')->setWidth(35);
                $sheet->getColumnDimension('C')->setWidth(15);
                $sheet->getColumnDimension('D')->setWidth(20);
                $sheet->getColumnDimension('E')->setWidth(40);

                foreach (range('F', 'U') as $col) {
                    if (! in_array($col, ['A', 'B', 'C', 'D', 'E'])) {
                        $sheet->getColumnDimension($col)->setWidth(18);
                    }
                }
            },
        ];
    }
}
