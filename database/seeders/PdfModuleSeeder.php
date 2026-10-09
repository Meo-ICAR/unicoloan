<?php

namespace Database\Seeders;

use App\Enums\PdfModuleClientScope;
use App\Models\PdfModule;
use Illuminate\Database\Seeder;

class PdfModuleSeeder extends Seeder
{
    /**
     * Moduli noti: file => [tipi_prodotto|null, ambito cliente].
     *
     * @var array<string, array{0: array<int, string>|null, 1: PdfModuleClientScope}>
     */
    private const MODULES = [
        '20240403 - proforma fattura mutuo NO IVA.pdf' => [['Mutuo', 'IPOTECARIO'], PdfModuleClientScope::Entrambi],
        'Compenso di mediazione fuori convenzione.pdf' => [null, PdfModuleClientScope::Entrambi],
        'Delega richiesta allegati statali_compressed.pdf' => [null, PdfModuleClientScope::PersonaFisica],
        'NUOVA  Infomativa privacy 2025 - Editato_compressed.pdf' => [null, PdfModuleClientScope::Entrambi],
        'QAV Persona fisica_compressed.pdf' => [null, PdfModuleClientScope::PersonaFisica],
        'QAV Persona giuridica_compressed.pdf' => [null, PdfModuleClientScope::PersonaGiuridica],
        'Races VERS. 03_2026 - Fascicolo completo retail  MUTUI compilabile_compressed.pdf' => [['Mutuo', 'IPOTECARIO'], PdfModuleClientScope::PersonaFisica],
        'Races VERS. 03_2026 - Fascicolo completo retail  Prestiti Personali compilabile_compressed.pdf' => [['Prestito', 'CHIROGRAFARIO', 'Microcredito'], PdfModuleClientScope::PersonaFisica],
        'Races VERS. 03_2026 - Fascicolo completo retail  TFS compilabile_compressed.pdf' => [['TFS'], PdfModuleClientScope::PersonaFisica],
        'Races VERS. 03_2026 - Fascicolo completo retail CQ compilabile_compressed.pdf' => [['Cessione', 'Delega'], PdfModuleClientScope::PersonaFisica],
        'Races VERS. 06_2025 - Fascicolo completo Corporate compilabile_compressed.pdf' => [['Aziendale', 'PRESTITO AZIENDALE'], PdfModuleClientScope::PersonaGiuridica],
        'Richiesta Conteggio Estintivo_compressed.pdf' => [null, PdfModuleClientScope::PersonaFisica],
        'patronato_compressed.pdf' => [null, PdfModuleClientScope::PersonaFisica],
    ];

    public function run(): void
    {
        foreach (self::MODULES as $file => [$tipi, $scope]) {
            PdfModule::firstOrCreate(
                ['file_path' => 'module/'.$file],
                [
                    'name' => self::nameFromFile($file),
                    'tipi_prodotto' => $tipi,
                    'client_scope' => $scope,
                    'is_active' => true,
                ],
            );
        }
    }

    private static function nameFromFile(string $file): string
    {
        $name = str_replace('_compressed', '', pathinfo($file, PATHINFO_FILENAME));

        return trim((string) preg_replace('/\s+/', ' ', $name));
    }
}
