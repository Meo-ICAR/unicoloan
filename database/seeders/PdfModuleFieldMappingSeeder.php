<?php

namespace Database\Seeders;

use App\Enums\ModuleFormatter as Fmt;
use App\Enums\ModuleSourceKey as Key;
use App\Models\PdfModule;
use Illuminate\Database\Seeder;

/**
 * Mappatura iniziale campo PDF -> dato, ricavata leggendo il testo accanto a ogni campo
 * dei moduli. Si mappano solo i campi chiari e i dati disponibili: luogo/data di nascita,
 * cittadinanza, dati del collaboratore e questionari restano da compilare a mano.
 *
 * Idempotente: tocca solo i campi ancora senza `source_key`, quindi non sovrascrive
 * mappature fatte da Filament, salvo le voci con terzo elemento `true` (forzate). Eseguire dopo PdfModuleSeeder e modules:sync-fields.
 */
class PdfModuleFieldMappingSeeder extends Seeder
{
    /**
     * @return array<string, array<string, array{0: Key, 1?: Fmt|null, 2?: bool}>>
     */
    private function mappings(): array
    {
        $oggi = [Key::PraticaOggi, Fmt::DateIt];
        $importo = [Key::PraticaImporto, Fmt::MoneyIt];

        // Struttura comune dei fascicoli Races retail (Prestiti, Mutui).
        $racesRetail = [
            'Text1' => [Key::ClienteNominativo],
            'Text3' => [Key::ClienteCodiceFiscale],
            'Text16' => $oggi,
            'Text5' => $oggi,
            'nuovo1' => [Key::ClienteCognome],
            'nuovo2' => [Key::ClienteNome],
            'nuovo3' => [Key::ClienteCodiceFiscale],
            'nuovo4' => [Key::ClienteLuogoNascita],
            'nuovo5' => [Key::ClienteDataNascita, Fmt::DateIt],
            'Text6' => [Key::SedeIndirizzo],
            'Text7' => [Key::SedeCivico],
            'Text8' => [Key::SedeCap],
            'Text9' => [Key::SedeCitta],
            'Text10' => [Key::SedeProvincia],
            'Text11' => [Key::ClienteTelefono],
            'Text12' => [Key::ClienteEmail],
            'Text26' => $importo,
            'dummyFieldName1' => [Key::AgenteNominativo],
            'Text2' => [Key::AgenteIndirizzo],
            'dummyFieldName2' => [Key::AgenteEmail],
            'Text4' => [Key::AgenteTelefono],
        ];

        return [
            '20240403 - proforma fattura mutuo NO IVA.pdf' => [
                'cliente' => [Key::ClienteNominativo],
                'indirizzo' => [Key::SedeIndirizzoCompleto],
                'codice_fiscale' => [Key::ClienteCodiceFiscale],
                'data_proforma_af_date' => $oggi,
            ],
            'Compenso di mediazione fuori convenzione.pdf' => [
                'Banca' => [Key::PraticaBanca],
                'Collaboratore' => [Key::AgenteNominativo],
                'Codice Fiscale' => [Key::AgenteCodiceFiscale],
                'Intestatarario' => [Key::ClienteNominativo],
                'Richiesta' => $importo,
                'Durata' => [Key::PraticaNumeroRate],
                'presentato il' => [Key::PraticaDataInserimento, Fmt::DateIt],
                'Data8_af_date' => $oggi,
            ],
            'Delega richiesta allegati statali_compressed.pdf' => [
                'cliente' => [Key::ClienteNominativo],
                'natoa a' => [Key::ClienteLuogoNascita],
                'residenza' => [Key::SedeIndirizzoCompleto],
                'Data2_af_date' => $oggi,
            ],
            'NUOVA  Infomativa privacy 2025 - Editato_compressed.pdf' => [
                'Text1' => [Key::ClienteNominativo],
                'Text2' => [Key::ClienteCodiceFiscale],
                'Text3' => $oggi,
            ],
            'QAV Persona fisica_compressed.pdf' => [
                'Text1' => [Key::ClienteCognome],
                'Text2' => [Key::ClienteNome],
                'Text4' => [Key::ClienteCodiceFiscale],
                'Text5' => [Key::ClienteLuogoNascita],
                'Text6' => [Key::ClienteDataNascita, Fmt::DateIt],
                'Text7' => [Key::ClienteCittadinanza],
                'Text8' => [Key::SedeCitta],
                'Text11' => [Key::SedeProvincia],
                'Text12' => [Key::SedeIndirizzo],
                'Text13' => [Key::SedeCap],
                'Text15' => [Key::DocumentoNumero],
                'Text16' => [Key::DocumentoRilasciatoDa],
                'Text17' => [Key::DocumentoRilasciatoIl, Fmt::DateIt],
                'data' => $oggi,
            ],
            'QAV Persona giuridica_compressed.pdf' => [
                'dummyFieldName10' => [Key::ClienteCognome],
                'dummyFieldName11' => [Key::ClienteCodiceFiscale],
                'dummyFieldName12' => [Key::SedeIndirizzoCompleto],
                'dummyFieldName14' => [Key::ClienteCciaa],
                'dummyFieldName15' => [Key::ClienteAteco],
                'dummyFieldName16' => [Key::ClientePec],
                'dummyFieldName17' => [Key::ClienteEmail],
                'dummyFieldName19' => [Key::ClienteTelefono],
            ],
            'Races VERS. 03_2026 - Fascicolo completo retail  MUTUI compilabile_compressed.pdf' => $racesRetail,
            'Races VERS. 03_2026 - Fascicolo completo retail  Prestiti Personali compilabile_compressed.pdf' => $racesRetail,
            'Races VERS. 03_2026 - Fascicolo completo retail  TFS compilabile_compressed.pdf' => [
                'Text1' => [Key::ClienteNominativo],
                'Text2' => [Key::ClienteCodiceFiscale],
                'data' => $oggi,
                'nuovo1' => [Key::ClienteCognome],
                'nuovo2' => [Key::ClienteNome],
                'nuovo3' => [Key::ClienteCodiceFiscale],
                'nuovo4' => [Key::ClienteLuogoNascita],
                'nuovo5' => [Key::ClienteDataNascita, Fmt::DateIt],
                'Text10' => [Key::SedeIndirizzo],
                'Text11' => [Key::SedeCap],
                'Text12' => [Key::SedeCitta],
                'Text13' => [Key::SedeProvincia],
                'Text14' => [Key::ClienteTelefono],
                'Text15' => [Key::ClienteEmail],
                'Text16' => $importo,
                'dummyFieldName1' => [Key::AgenteNominativo],
                'dummyFieldName2' => [Key::AgenteIndirizzo],
                'Text4' => [Key::AgenteEmail],
                'Text5' => [Key::AgenteTelefono],
            ],
            'Races VERS. 03_2026 - Fascicolo completo retail CQ compilabile_compressed.pdf' => [
                'Text1' => [Key::ClienteNominativo],
                'Text3' => [Key::ClienteCodiceFiscale],
                'Text16' => $oggi,
                'Text5' => $oggi,
                'nuovo1' => [Key::ClienteCognome],
                'nuovo2' => [Key::ClienteNome],
                'nuovo3' => [Key::ClienteCodiceFiscale],
                'nuovo4' => [Key::ClienteLuogoNascita],
                'nuovo5' => [Key::ClienteDataNascita, Fmt::DateIt],
                'Text6' => [Key::SedeIndirizzo],
                'Text8' => [Key::SedeCivico],
                'Text9' => [Key::SedeCap],
                'Text10' => [Key::SedeCitta],
                'Text11' => [Key::SedeProvincia],
                'Text12' => [Key::ClienteTelefono],
                'Text13' => [Key::ClienteEmail],
                'testo 1' => [Key::PraticaProdotto],
                'testo 2' => $importo,
                'dummyFieldName1' => [Key::AgenteNominativo],
                'Text2' => [Key::AgenteIndirizzo],
                'dummyFieldName2' => [Key::AgenteEmail],
                'Text4' => [Key::AgenteTelefono],
            ],
            'Races VERS. 06_2025 - Fascicolo completo Corporate compilabile_compressed.pdf' => [
                'Text1' => [Key::ClienteNominativo],
                'Text3' => [Key::ClienteCodiceFiscale],
                'Text16' => $oggi,
                'Text5' => $oggi,
                'dummyFieldName3' => [Key::ClienteCognome],
                'dummyFieldName4' => [Key::ClienteCodiceFiscale],
                'dummyFieldName5' => [Key::SedeIndirizzoCompleto],
                'dummyFieldName6' => [Key::ClienteEmail],
                'dummyFieldName8' => [Key::ClienteTelefono],
                'Text7' => [Key::RappresentanteNominativo],
                'Text8' => [Key::RappresentanteEmail],
                'Text9' => [Key::RappresentanteTelefono],
                'Text10' => [Key::PraticaProdotto],
                'dummyFieldName7' => [Key::ClientePec],
                'Text11' => $importo,
                'dummyFieldName1' => [Key::AgenteNominativo],
                'Text2' => [Key::AgenteIndirizzo],
                'dummyFieldName2' => [Key::AgenteEmail],
                'Text4' => [Key::AgenteTelefono],
            ],
            'Richiesta Conteggio Estintivo_compressed.pdf' => [
                'Spettabile 1' => [Key::TerziBanca, null, true],
                'tipo contratto' => [Key::TerziProdotto],
                'O Cessione del quinto dello Stipendio con rata di €' => [Key::TerziRata, Fmt::MoneyIt],
                'Io sottoscritto' => [Key::ClienteNominativo],
                'nato a' => [Key::ClienteLuogoNascita],
                'residente in' => [Key::SedeCitta],
                'alla via' => [Key::SedeIndirizzo],
                'Data1_af_date' => $oggi,
            ],
            'patronato_compressed.pdf' => [
                'nome' => [Key::ClienteNominativo],
                'nato_a' => [Key::ClienteLuogoNascita],
                'nato_il' => [Key::ClienteDataNascita, Fmt::DateIt],
                'cittad' => [Key::ClienteCittadinanza],
                'citta' => [Key::SedeCitta],
                'prov_res' => [Key::SedeProvincia],
                'cap' => [Key::SedeCap],
                'via' => [Key::SedeIndirizzo],
                'civico' => [Key::SedeCivico],
                'cf' => [Key::ClienteCodiceFiscale],
                'data' => $oggi,
                'Nome' => [Key::ClienteNominativo],
                'com_nasc' => [Key::ClienteLuogoNascita],
                'dt_nasc' => [Key::ClienteDataNascita, Fmt::DateIt],
                'com_res' => [Key::SedeCitta],
                'cap_res' => [Key::SedeCap],
                'indir_res' => [Key::SedeIndirizzo],
                'cod_fisc' => [Key::ClienteCodiceFiscale],
                'dummyFieldName3' => [Key::SedeProvincia],
                'dummyFieldName4' => [Key::SedeCivico],
            ],
        ];
    }

    public function run(): void
    {
        foreach ($this->mappings() as $file => $fields) {
            $module = PdfModule::where('file_path', 'module/'.$file)->first();

            if ($module === null) {
                $this->command?->warn("Modulo non registrato, saltato: {$file}");

                continue;
            }

            $mapped = 0;

            foreach ($fields as $name => $definition) {
                [$key, $formatter, $force] = [$definition[0], $definition[1] ?? null, $definition[2] ?? false];

                $mapped += $module->fields()
                    ->where('pdf_field_name', $name)
                    ->when(! $force, fn ($query) => $query->whereNull('source_key'))
                    ->update([
                        'source_key' => $key->value,
                        'formatter' => $formatter?->value,
                    ]);
            }

            $this->command?->line("{$module->name}: {$mapped} campi mappati");
        }
    }
}
