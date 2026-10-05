<?php

namespace App\Filament\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class M510InformativoSheet implements FromArray, WithTitle, WithEvents
{
    protected array $dati;

    public function __construct(array $dati)
    {
        $this->dati = $dati;
    }

    public function array(): array
    {
        return [
            // RIGA 1: Intestazione principale
            [
                "Numero iscrizione\n(M510)",
                '4 di 5 _ PROFILO INFORMATIVO E DI TRASPARENZA',
                '', '', '', '', ''
            ],
            // RIGA 2: MPI1
            [
                'MPI1',
                "NUMERO SITI WEB UTILIZZATI PER LO SVOGLIMENTO DELL'ATTIVITA' TIPICA",
                $this->dati['numero_siti'] ?? '0',
                '', '', '', ''
            ],
            // RIGA 3: MPI2 Intestazioni Domini
            [
                'MPI2',
                "SITI WEB UTILIZZATI PER LO SVOLGIMENTO DELL'ATTIVITA' TIPICA",
                'DOMINIO 1', 'DOMINIO 2', 'DOMINIO 3', 'DOMINIO 4', 'DOMINIO 5'
            ],
            // RIGA 4: MPI2 Valori Domini
            [
                '',
                '',
                $this->dati['siti'][0] ?? '',
                $this->dati['siti'][1] ?? '',
                $this->dati['siti'][2] ?? '',
                $this->dati['siti'][3] ?? '',
                $this->dati['siti'][4] ?? ''
            ],
            // RIGA 5: MPI3
            [
                'MPI3',
                'DATA ULTIMO AGGIORNAMENTO TRASPARENZA SITO INTERNET',
                $this->dati['data_trasparenza'] ?? '',
                '', '', '', ''
            ],
            // RIGA 6: MPI4
            [
                'MPI4',
                'DATA ULTIMO AGGIORNAMENTO RELAZIONE SUI REQUISITI ORGANIZZATIVI',
                $this->dati['data_relazione_requisiti'] ?? '',
                '', '', '', ''
            ],
            // RIGA 7: MPI5
            [
                'MPI5',
                'DATA ULTIMO AGGIORNAMENTO PROCEDURE (INDICARE QUALE/I)',
                $this->dati['procedure'][0] ?? '',
                $this->dati['procedure'][1] ?? '',
                $this->dati['procedure'][2] ?? '',
                $this->dati['procedure'][3] ?? '',
                ''
            ],
            // RIGA 8: MPI6
            [
                'MPI6',
                'INDICAZIONE DELLE FUNZIONI DI CONTROLLO ESTERNALIZZATE',
                $this->dati['funzioni_esternalizzate'][0] ?? '',
                $this->dati['funzioni_esternalizzate'][1] ?? '',
                $this->dati['funzioni_esternalizzate'][2] ?? '',
                '',
                ''
            ],
            // RIGA 9: MPI7 Intestazioni Modulistica
            [
                'MPI7',
                'DATA ULTIMO AGGIORNAMENTO DELLA MODULISTICA',
                $this->dati['moduli'][0] ?? '',
                $this->dati['moduli'][1] ?? '',
                $this->dati['moduli'][2] ?? '',
                $this->dati['moduli'][3] ?? '',
                ''
            ],
            // RIGA 10: MPI7-A Valori Modulistica
            [
                '',
                'MPI7-A',
                $this->dati['modulistica_a'][0] ?? '',
                $this->dati['modulistica_a'][1] ?? '',
                $this->dati['modulistica_a'][2] ?? '',
                $this->dati['modulistica_a'][3] ?? '',
                ''
            ],
        ];
    }

    public function title(): string
    {
        return 'Profilo Informativo';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Unione celle in base all'immagine
                $sheet->mergeCells('B1:G1');  // Titolo Header
                $sheet->mergeCells('C2:G2');  // Valore MPI1
                $sheet->mergeCells('C5:G5');  // Valore MPI3
                $sheet->mergeCells('C6:G6');  // Valore MPI4

                // Unione verticale per MPI2 (perché occupa 2 righe)
                $sheet->mergeCells('A3:A4');
                $sheet->mergeCells('B3:B4');

                // Stile Header e colonna descrittiva (colore verde-acqua scuro e chiaro)
                $headerStyle = [
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FF008B8B']  // Dark Cyan
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true
                    ],
                ];

                $colStyle = [
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FF48D1CC']  // Medium Turquoise
                    ],
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true
                    ],
                ];

                // Applicazione stili
                $sheet->getStyle('A1:G1')->applyFromArray($headerStyle);
                $sheet->getStyle('A2:B9')->applyFromArray($colStyle);
                $sheet->getStyle('C9:F9')->applyFromArray($headerStyle);  // Intestazioni MPI7

                // Centrare il testo delle celle di input
                $sheet
                    ->getStyle('C2:G10')
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                // Autoresize delle colonne
                foreach (range('A', 'G') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
            },
        ];
    }
}
