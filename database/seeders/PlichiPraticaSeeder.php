<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use App\Models\PraticaStati;
use App\Models\Task;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Plichi documentali delle pratiche: un plico per ogni prodotto e stato di lavorazione (pratiches_statos),
 * con i tipi documento richiesti; piu' i plichi del cliente per stato di verifica.
 * Gli stati sono quelli gia' presenti in `pratiches_statos` (proforma): il plico usa la grafia salvata in quella tabella.
 * Il plico si attiva quando tipo_prodotto e stato_pratica (confronto senza maiuscole) coincidono.
 * Idempotente: si puo' rilanciare dopo aver cambiato le mappe qui sotto.
 */
class PlichiPraticaSeeder extends Seeder
{
    /**
     * Tipi documento non ancora presenti, creati se mancano: nome => [firma richiesta].
     *
     * @var array<string, bool>
     */
    private const NEW_TYPES = [
        'Codice fiscale / Tessera sanitaria' => false,
        'Busta paga' => false,
        'Certificazione Unica (CU)' => false,
        'Modello 730 / Redditi' => false,
        'Estratto conto bancario' => false,
        'Contratto di lavoro' => false,
        'Certificato di stipendio e quota cedibile' => false,
        'Quantificazione TFS/TFR' => false,
        'Visura camerale' => false,
        'Bilanci ultimi esercizi' => false,
        'Preliminare di compravendita' => false,
        'Perizia immobiliare' => false,
        'Atto di provenienza' => false,
        'Polizza assicurativa' => false,
        'Delibera banca' => false,
        'Contratto di finanziamento' => true,
        'Informazioni europee di base sul credito (SECCI)' => true,
        'Notifica al datore di lavoro' => false,
        'Benestare del datore di lavoro o ente' => false,
        'Contabile di liquidazione' => false,
        'Preventivo fornitore' => false,
        'Contratto utenza' => false,
    ];

    /**
     * Famiglie di prodotti e documenti per stato: stato => [[tipo documento (anche parte del nome), obbligatorio], ...].
     * Un nome tra parentesi quadre dopo il segno "~" indica una ricerca per parte del nome.
     *
     * @return array<string, array{products: array<int, string>, states: array<string, array<int, array{0: string, 1: bool}>>}>
     */
    private function families(): array
    {
        $ident = [['Carta di Identità', true], ['Codice fiscale / Tessera sanitaria', true], ['Patente di Guida', false]];
        $contract = [['Delibera banca', true], ['Informazioni europee di base sul credito (SECCI)', true]];
        $closing = ['PERFEZIONATA' => [['Contratto di finanziamento', true]], 'LIQUIDATA' => [['Contabile di liquidazione', true]]];
        $privacy = ['~NUOVA Infomativa privacy', true];
        $fuoriConvenzione = ['Compenso di mediazione fuori convenzione', false];

        return [
            'cessione' => [
                'products' => ['Cessione', 'Delega'],
                'states' => [
                    'Inserita' => $ident,
                    'ACCETTATO PREVENTIVO' => [['~Fascicolo completo retail CQ', true], $privacy, $fuoriConvenzione],
                    'Richiesta Istruttoria' => [['Busta paga', true], ['Certificazione Unica (CU)', true], ['Certificato di stipendio e quota cedibile', true], ['Delega richiesta allegati statali', false], ['patronato', false], ['Richiesta Conteggio Estintivo', false]],
                    'Richiesta Polizza' => [['Polizza assicurativa', true]],
                    'DELIBERATA' => $contract,
                    'NOTIFICA' => [['Notifica al datore di lavoro', true]],
                    'RIENTRO BENESTARE' => [['Benestare del datore di lavoro o ente', true]],
                ] + $closing,
            ],
            'tfs' => [
                'products' => ['TFS'],
                'states' => [
                    'Inserita' => $ident,
                    'ACCETTATO PREVENTIVO' => [['~Fascicolo completo retail TFS', true], $privacy, $fuoriConvenzione],
                    'Richiesta Istruttoria' => [['Busta paga', true], ['Quantificazione TFS/TFR', true], ['Delega richiesta allegati statali', false], ['Richiesta Conteggio Estintivo', false]],
                    'DELIBERATA' => $contract,
                    'NOTIFICA' => [['Notifica al datore di lavoro', true]],
                    'RIENTRO BENESTARE' => [['Benestare del datore di lavoro o ente', true]],
                ] + $closing,
            ],
            'prestito' => [
                'products' => ['Prestito', 'CHIROGRAFARIO', 'Microcredito'],
                'states' => [
                    'Inserita' => $ident,
                    'ACCETTATO PREVENTIVO' => [['~Fascicolo completo retail Prestiti Personali', true], $privacy, $fuoriConvenzione],
                    'Richiesta Istruttoria' => [['Busta paga', true], ['Certificazione Unica (CU)', true], ['Estratto conto bancario', true], ['Contratto di lavoro', false], ['Modello 730 / Redditi', false]],
                    'DELIBERATA' => $contract,
                ] + $closing,
            ],
            'mutuo' => [
                'products' => ['Mutuo', 'IPOTECARIO'],
                'states' => [
                    'Inserita' => $ident,
                    'ACCETTATO PREVENTIVO' => [['~Fascicolo completo retail MUTUI', true], $privacy, $fuoriConvenzione],
                    'Richiesta Istruttoria' => [['Busta paga', true], ['Certificazione Unica (CU)', true], ['Modello 730 / Redditi', true], ['Estratto conto bancario', true], ['Preliminare di compravendita', true], ['Atto di provenienza', false]],
                    'In attesa documenti originali' => [['Perizia immobiliare', true], ['Atto di provenienza', true]],
                    'Richiesta Polizza' => [['Polizza assicurativa', true]],
                    'DELIBERATA' => $contract,
                    'PERFEZIONATA' => [['Contratto di finanziamento', true], ['~proforma fattura mutuo', true]],
                    'LIQUIDATA' => [['Contabile di liquidazione', true]],
                ],
            ],
            'corporate' => [
                'products' => ['Aziendale', 'PRESTITO AZIENDALE', 'LEASING'],
                'states' => [
                    'Inserita' => [['Carta di Identità', true], ['Visura camerale', true], ['Codice fiscale / Tessera sanitaria', false]],
                    'ACCETTATO PREVENTIVO' => [['~Fascicolo completo Corporate', true], $privacy, $fuoriConvenzione],
                    'Richiesta Istruttoria' => [['Bilanci ultimi esercizi', true], ['Modello 730 / Redditi', true], ['Estratto conto bancario', true], ['Preventivo fornitore', false]],
                    'DELIBERATA' => $contract,
                ] + $closing,
            ],
            'polizza' => [
                'products' => ['Polizza'],
                'states' => ['Inserita' => $ident, 'PERFEZIONATA' => [['Polizza assicurativa', true]]],
            ],
            'utenza' => [
                'products' => ['Utenza'],
                'states' => ['Inserita' => [['Carta di Identità', true], ['Contratto utenza', true]]],
            ],
        ];
    }

    public function run(): void
    {
        $this->ensureDocumentTypes();

        $known = PraticaStati::query()->pluck('stato_pratica')->mapWithKeys(fn (string $name): array => [mb_strtolower($name) => $name])->all();
        $count = 0;

        foreach ($this->families() as $family) {
            foreach ($family['products'] as $product) {
                foreach ($family['states'] as $state => $documents) {
                    if (! isset($known[mb_strtolower($state)])) {
                        $this->command?->warn("Stato {$state} non presente in pratiches_statos: plico {$product} saltato.");

                        continue;
                    }

                    $state = $known[mb_strtolower($state)];

                    $this->plico("{$product} · {$state}", "Documenti del prodotto {$product} nello stato {$state}.", 'pratica', [
                        'trigger_field' => 'tipo_prodotto', 'trigger_state' => 'equals', 'trigger_value' => $product,
                        'trigger_subfield' => 'stato_pratica', 'trigger_subvalue' => $state,
                    ], $documents);
                    $count++;
                }
            }
        }

        $count += $this->clientPlichi();

        $this->command?->info("Plichi creati o aggiornati: {$count}");
    }

    /**
     * Plichi del cliente per stato di verifica (campo status) e tipo (persona fisica o giuridica).
     */
    private function clientPlichi(): int
    {
        $common = [['Carta di Identità', true], ['Codice fiscale / Tessera sanitaria', true]];
        $plichi = [
            ['Cliente persona fisica · raccolta dati', '1', 'raccolta_dati', [...$common, ['Modulo Privacy', true], ['Modulo AML', true]]],
            ['Cliente persona giuridica · raccolta dati', '0', 'raccolta_dati', [['Carta di Identità', true], ['Visura camerale', true], ['Modulo Privacy', true], ['Modulo AML', true]]],
            ['Cliente persona fisica · valutazione AML', '1', 'valutazione_aml', [['QAV Persona fisica', true]]],
            ['Cliente persona giuridica · valutazione AML', '0', 'valutazione_aml', [['QAV Persona giuridica', true]]],
        ];

        foreach ($plichi as [$name, $isPerson, $status, $documents]) {
            $this->plico($name, 'Documenti del cliente nello stato di verifica '.$status.'.', 'client', [
                'trigger_field' => 'status', 'trigger_state' => 'equals', 'trigger_value' => $status,
                'trigger_subfield' => 'is_person', 'trigger_subvalue' => $isPerson,
            ], $documents);
        }

        return count($plichi);
    }

    /**
     * @param  array<string, string>  $conditions
     * @param  array<int, array{0: string, 1: bool}>  $documents
     */
    private function plico(string $name, string $description, string $taskable, array $conditions, array $documents): void
    {
        $task = Task::query()->withoutGlobalScopes()->updateOrCreate(
            ['name' => $name],
            array_merge(['description' => $description, 'taskable' => $taskable, 'is_active' => true], $conditions),
        );

        $sync = [];

        foreach ($documents as [$label, $required]) {
            $type = $this->findType($label);

            if ($type === null) {
                $this->command?->warn("Tipo documento «{$label}» non trovato: saltato nel plico {$name}.");

                continue;
            }

            $sync[$type->getKey()] = ['is_required' => $required, 'slug' => Str::slug($name.' '.$type->name)];
        }

        $task->documentTypes()->sync($sync);
    }

    private function findType(string $label): ?DocumentType
    {
        return str_starts_with($label, '~')
            ? DocumentType::query()->where('name', 'like', '%'.substr($label, 1).'%')->first()
            : DocumentType::query()->where('name', $label)->first();
    }

    /**
     * Crea i tipi mancanti e segna come proponibili per clienti e pratiche quelli di identita' e di modulistica.
     */
    private function ensureDocumentTypes(): void
    {
        foreach (self::NEW_TYPES as $name => $signed) {
            DocumentType::query()->firstOrCreate(['slug' => Str::slug($name)], [
                'name' => $name,
                'nature' => 'incoming',
                'is_signed' => $signed,
                'is_client' => true,
                'is_practice' => true,
            ]);
        }

        DocumentType::query()->whereIn('name', ['Carta di Identità', 'Patente di Guida'])->update(['is_client' => true, 'is_practice' => true]);
        DocumentType::query()->whereIn('name', ['Modulo AML', 'Modulo Privacy'])->update(['is_client' => true]);
    }
}
