<?php

namespace App\Console\Commands;

use App\Enums\DocumentStatus;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\PROFORMA\Fornitore;
use App\Models\PROFORMA\Pratica;
use App\Services\CodiceFiscaleDecoder;
use App\Services\CompanyResolver;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CreateMissingClients extends Command
{
    private const DEFAULT_IBAN = 'IT1234567890123456';

    private const DEFAULT_SALARY = 999;

    private const DEFAULT_CITIZENSHIP = 'Italiana';

    /**
     * @var array<string, array{type: string, prefix: string, emitted_by: string, emitted_at: string}>
     */
    private const PLACEHOLDER_DOCUMENTS = [
        "Carta d'Identità" => ['type' => 'Carta di Identità o Patente & cert. Residenza', 'prefix' => 'AA', 'emitted_by' => 'Comune di Roma', 'emitted_at' => '2023-01-01'],
        'Patente di Guida' => ['type' => 'Patente di Guida', 'prefix' => 'U1', 'emitted_by' => 'Motorizzazione Civile di Roma', 'emitted_at' => '2022-06-01'],
        'Certificato di Residenza' => ['type' => 'Carta di Identità o Patente & cert. Residenza', 'prefix' => 'CR', 'emitted_by' => 'Comune di Napoli', 'emitted_at' => '2026-01-01'],
    ];

    private const DEFAULT_PEC_DOMAIN = 'pec.example.test';

    private const DEFAULT_ATECO = '62.01.00';

    private const DEFAULT_THIRD_PARTY_BANK = 'Finanziaria Terza S.p.A.';

    private const DEFAULT_THIRD_PARTY_PRODUCT = 'Cessione del quinto dello stipendio';

    private const DEFAULT_THIRD_PARTY_INSTALMENT = 300;

    private const DEFAULT_BIRTH_PLACE = 'Roma (RM)';

    private const DEFAULT_PHONE = '3331234567';

    private const DEFAULT_STREET_NUMBER = '1';

    private const DEFAULT_AMOUNT = 10000;

    private const DEFAULT_INSTALMENTS = 72;

    private const DEFAULT_BANK = 'Banca Test';

    private ?string $companyId = null;

    protected $signature = 'clients:create-missing {--dry-run : Mostra cosa verrebbe creato senza scrivere nulla}
        {--code=* : Limita ai codici fiscali / P.IVA indicati}
        {--pratica=* : Con --code, assegna i codici finti solo alle pratiche senza codice indicate}';

    protected $description = 'Crea i clienti mancanti a partire dai dati delle pratiche (codice fiscale / P.IVA senza cliente)';

    public function handle(): int
    {
        $assigned = $this->assignPlaceholderTaxCodes();

        $existing = Client::query()
            ->get(['tax_code', 'vat_number'])
            ->flatMap(fn (Client $client) => [$client->tax_code, $client->vat_number])
            ->filter()
            ->map(fn (string $code) => Str::upper(trim($code)))
            ->flip();

        // Dalla pratica piu' recente di ogni codice si prendono i dati anagrafici.
        // Gli impegni di terzi non sono pratiche: non generano clienti.
        $groups = Pratica::query()
            ->where('is_notowned', false)
            ->whereNotNull('codice_fiscale')
            ->where('codice_fiscale', '!=', '')
            ->orderByDesc('data_inserimento_pratica')
            ->get(['codice_fiscale', 'nome_cliente', 'cognome_cliente', 'denominazione_agente', 'partita_iva_agente'])
            ->groupBy(fn (Pratica $pratica) => Str::upper(trim($pratica->codice_fiscale)));

        $only = collect($this->option('code'))->map(fn (string $code) => Str::upper(trim($code)));

        if ($only->isNotEmpty()) {
            $groups = $groups->filter(fn ($group, string $code) => $only->contains($code));
        }

        $created = 0;
        $skipped = 0;

        foreach ($groups as $code => $group) {
            if ($existing->has($code)) {
                continue;
            }

            $attributes = $this->attributesFor($code, $group->first());

            if ($attributes === null) {
                $skipped++;

                continue;
            }

            if (! $this->option('dry-run')) {
                $this->createDefaultBranches(Client::create($attributes));
            }

            $created++;
        }

        // Le chiavi numeriche (P.IVA) diventano int dopo il groupBy: si riportano a stringa per le query.
        $codes = $groups->keys()->map(fn ($code) => (string) $code);

        $reclassified = $this->reclassifyNamelessPersons($codes);
        $backfilled = $this->backfillBranches($codes);
        $forced = $this->forceDefaultFinancials($codes);
        $personal = $this->fillPersonalData();
        $administrators = $this->createLegalRepresentatives($codes);
        $contacts = $this->fillPlaceholderContacts($codes);
        $documents = $this->createPlaceholderIdentityDocuments($codes);
        $pratiche = $this->fillPlaceholderPraticaData($codes);
        $companyData = $this->fillPlaceholderCompanyData($codes);
        $createdSuppliers = $this->createMissingSuppliers();
        $suppliers = $this->fillPlaceholderSupplierData();
        $thirdParty = $this->createPlaceholderThirdPartyFinancings($codes, $groups);

        $this->info(($this->option('dry-run') ? '[dry-run] Da creare: ' : 'Creati: ').$created.' clienti');

        if ($skipped > 0) {
            $this->warn("Saltati {$skipped} codici senza nome ne' cognome.");
        }

        $this->info(($this->option('dry-run') ? '[dry-run] Pratiche senza codice fiscale da codificare: ' : 'Pratiche senza codice fiscale con codice finto: ').$assigned);
        $this->info(($this->option('dry-run') ? '[dry-run] Persone senza nome da trasformare in societa: ' : 'Persone senza nome trasformate in societa: ').$reclassified);
        $this->info(($this->option('dry-run') ? '[dry-run] Clienti con IBAN/stipendio da forzare: ' : 'Clienti con IBAN/stipendio forzati: ').$forced);
        $this->info(($this->option('dry-run') ? '[dry-run] Persone con dati anagrafici da completare: ' : 'Persone con dati anagrafici completati: ').$personal);
        $this->info(($this->option('dry-run') ? '[dry-run] Societa senza legale rappresentante: ' : 'Amministratori dummy creati: ').$administrators);
        $this->info(($this->option('dry-run') ? '[dry-run] Clienti con contatti/civico da completare: ' : 'Clienti con contatti/civico completati: ').$contacts);
        $this->info(($this->option('dry-run') ? '[dry-run] Persone senza documento d\'identita\': ' : 'Documenti d\'identita segnaposto creati: ').$documents);
        $this->info(($this->option('dry-run') ? '[dry-run] Pratiche con importo/rate/banca da completare: ' : 'Pratiche con importo/rate/banca completate: ').$pratiche);
        $this->info(($this->option('dry-run') ? '[dry-run] Societa con PEC/Ateco/CCIAA da completare: ' : 'Societa con PEC/Ateco/CCIAA completate: ').$companyData);
        $this->info(($this->option('dry-run') ? '[dry-run] Fornitori mancanti da creare: ' : 'Fornitori mancanti creati: ').$createdSuppliers);
        $this->info(($this->option('dry-run') ? '[dry-run] Fornitori con contatti da completare: ' : 'Fornitori con contatti completati: ').$suppliers);
        $this->info(($this->option('dry-run') ? '[dry-run] Persone senza finanziamento di terzi: ' : 'Finanziamenti di terzi segnaposto creati: ').$thirdParty);
        $this->info(($this->option('dry-run') ? '[dry-run] Clienti senza sedi: ' : 'Sedi di default aggiunte a clienti esistenti: ').$backfilled);

        return self::SUCCESS;
    }

    /**
     * Ai clienti delle pratiche che non hanno nessuna sede si aggiungono le sedi segnaposto.
     *
     * @param  Collection<int, string>  $codes
     */
    private function backfillBranches($codes): int
    {
        $count = 0;

        // Client e Branch stanno su database diversi: niente whereDoesntHave, controllo in due passaggi.
        $clients = Client::query()
            ->where(fn ($query) => $query->whereIn('tax_code', $codes)->orWhereIn('vat_number', $this->vatCodes($codes)))
            ->get();

        $withBranches = Branch::query()
            ->where('branchable_type', (new Client)->getMorphClass())
            ->whereIn('branchable_id', $clients->modelKeys())
            ->pluck('branchable_id')
            ->map(fn ($id) => (int) $id)
            ->flip();

        foreach ($clients->reject(fn (Client $client) => $withBranches->has($client->getKey())) as $client) {
            if (! $this->option('dry-run')) {
                $this->createDefaultBranches($client);
            }

            $count++;
        }

        return $count;
    }

    /**
     * Sedi segnaposto da completare: domicilio (00100 Roma) per tutti e, per le persone
     * fisiche, anche la residenza (80100 Napoli). La sede principale e' la residenza per le
     * persone fisiche e il domicilio per le societa'.
     */
    private function createDefaultBranches(Client $client): void
    {
        $client->branches()->create([
            'company_id' => $this->companyId(),
            'name' => 'Domicilio',
            'address' => 'da inserire',
            'zip_code' => '00100',
            'city' => 'Roma',
            'province' => 'RM',
            'is_main_office' => ! $client->is_person,
        ]);

        if ($client->is_person) {
            $client->branches()->create([
                'company_id' => $this->companyId(),
                'name' => 'Residenza',
                'address' => 'da inserire',
                'zip_code' => '80100',
                'city' => 'Napoli',
                'province' => 'NA',
                'is_main_office' => true,
            ]);
        }
    }

    /**
     * Dati segnaposto per l'ambiente di sviluppo: IBAN a tutti i clienti delle pratiche e
     * stipendio alle sole persone fisiche. Sovrascrive i valori esistenti; conta solo i clienti
     * che cambiano, cosi' una seconda esecuzione ritorna 0.
     *
     * @param  Collection<int, string>  $codes
     */
    private function forceDefaultFinancials($codes): int
    {
        $clients = Client::query()
            ->where(fn ($query) => $query->whereIn('tax_code', $codes)->orWhereIn('vat_number', $this->vatCodes($codes)));

        $changed = (clone $clients)
            ->where(fn ($query) => $query
                ->whereNull('iban')->orWhere('iban', '!=', self::DEFAULT_IBAN)
                ->orWhere(fn ($persons) => $persons->where('is_person', true)
                    ->where(fn ($salary) => $salary->whereNull('salary')->orWhere('salary', '!=', self::DEFAULT_SALARY))))
            ->count();

        if (! $this->option('dry-run')) {
            (clone $clients)->update(['iban' => self::DEFAULT_IBAN]);
            (clone $clients)->where('is_person', true)->update(['salary' => self::DEFAULT_SALARY]);
        }

        return $changed;
    }

    /**
     * `vat_number` e' numerica: confrontarla con codici fiscali alfanumerici fa fallire le UPDATE.
     *
     * @param  Collection<int, string>  $codes
     * @return array<int, string>
     */
    private function vatCodes($codes): array
    {
        return $codes->filter(fn (string $code) => ctype_digit($code))->values()->all();
    }

    /**
     * Data/luogo di nascita e sesso si ricavano dal codice fiscale, la cittadinanza e' forzata a
     * italiana. Si completano solo i campi vuoti delle persone fisiche; un codice non valido
     * lascia vuoti i campi derivati. Conta le persone modificate.
     */
    private function fillPersonalData(): int
    {
        $decoder = app(CodiceFiscaleDecoder::class);
        $count = 0;

        Client::query()
            ->where('is_person', true)
            ->where('is_dummy', false)
            ->where(fn ($query) => $query->whereNull('birth_date')->orWhereNull('birth_place')
                ->orWhereNull('sex')->orWhereNull('citizenship'))
            ->lazyById()
            ->each(function (Client $client) use ($decoder, &$count): void {
                // Con un codice fiscale non decodificabile si usano dati di nascita segnaposto.
                $decoded = $decoder->decode($client->tax_code) ?? [
                    'birth_date' => CarbonImmutable::create(1980, 1, 1),
                    'birth_place' => self::DEFAULT_BIRTH_PLACE,
                    'sex' => 'M',
                ];

                $updates = array_filter([
                    'birth_date' => $client->birth_date === null ? $decoded['birth_date'] : null,
                    'birth_place' => $client->birth_place === null ? $decoded['birth_place'] : null,
                    'sex' => $client->sex === null ? $decoded['sex'] : null,
                    'citizenship' => $client->citizenship === null ? self::DEFAULT_CITIZENSHIP : null,
                ]);

                if ($updates === []) {
                    return;
                }

                if (! $this->option('dry-run')) {
                    $client->update($updates);
                }

                $count++;
            });

        return $count;
    }

    /**
     * Per ogni societa' delle pratiche senza legale rappresentante crea il cliente dummy
     * "Amministratore di <societa'>" e lo collega.
     *
     * @param  Collection<int, string>  $codes
     */
    private function createLegalRepresentatives($codes): int
    {
        $count = 0;

        Client::query()
            ->where(fn ($query) => $query->whereIn('tax_code', $codes)->orWhereIn('vat_number', $this->vatCodes($codes)))
            ->where('is_person', false)
            ->whereNull('legal_representative_id')
            ->lazyById()
            ->each(function (Client $company) use (&$count): void {
                if (! $this->option('dry-run')) {
                    $administrator = Client::create([
                        'name' => 'Amministratore di '.$company->name,
                        'is_person' => true,
                        'is_company' => false,
                        'is_dummy' => true,
                        'citizenship' => self::DEFAULT_CITIZENSHIP,
                        'phone' => self::DEFAULT_PHONE,
                        'email' => 'amministratore'.$company->getKey().'@example.test',
                    ]);

                    $company->update(['legal_representative_id' => $administrator->getKey()]);
                }

                $count++;
            });

        return $count;
    }

    /**
     * Telefono ed email segnaposto per i clienti delle pratiche che non li hanno e numero civico
     * per le sedi segnaposto. Conta i clienti e le sedi modificati.
     *
     * @param  Collection<int, string>  $codes
     */
    private function fillPlaceholderContacts($codes): int
    {
        $count = 0;

        $this->praticaClients($codes)
            ->where(fn ($query) => $query->whereNull('phone')->orWhere('phone', '')
                ->orWhereNull('email')->orWhere('email', ''))
            ->lazyById()
            ->each(function (Client $client) use (&$count): void {
                if (! $this->option('dry-run')) {
                    $client->update([
                        'phone' => blank($client->phone) ? self::DEFAULT_PHONE : $client->phone,
                        'email' => blank($client->email) ? 'cliente'.$client->getKey().'@example.test' : $client->email,
                    ]);
                }

                $count++;
            });

        Client::query()
            ->where('is_dummy', true)
            ->where(fn ($query) => $query->whereNull('phone')->orWhere('phone', '')
                ->orWhereNull('email')->orWhere('email', ''))
            ->lazyById()
            ->each(function (Client $administrator) use (&$count): void {
                if (! $this->option('dry-run')) {
                    $administrator->update([
                        'phone' => blank($administrator->phone) ? self::DEFAULT_PHONE : $administrator->phone,
                        'email' => blank($administrator->email) ? 'amministratore'.$administrator->getKey().'@example.test' : $administrator->email,
                    ]);
                }

                $count++;
            });

        $branches = Branch::query()
            ->where('branchable_type', (new Client)->getMorphClass())
            ->where('address', 'da inserire')
            ->where(fn ($query) => $query->whereNull('street_number')->orWhere('street_number', ''));

        $count += (clone $branches)->count();

        if (! $this->option('dry-run')) {
            $branches->update(['street_number' => self::DEFAULT_STREET_NUMBER]);
        }

        return $count;
    }

    /**
     * Documenti segnaposto delle persone fisiche delle pratiche: carta d'identita', patente e
     * certificato di residenza, ciascuno come Document distinto collegato al cliente. Il tipo
     * del documento e' quello del DB (carta e certificato: tipo combinato; patente: tipo proprio).
     * Si crea solo cio' che manca, riconoscendo i documenti dal nome.
     *
     * @param  Collection<int, string>  $codes
     */
    private function createPlaceholderIdentityDocuments($codes): int
    {
        $types = DocumentType::whereIn('name', array_unique(array_column(self::PLACEHOLDER_DOCUMENTS, 'type')))
            ->get()
            ->keyBy('name');

        foreach (self::PLACEHOLDER_DOCUMENTS as $definition) {
            if (! $types->has($definition['type'])) {
                $this->warn("Tipo documento non trovato: {$definition['type']}. Documenti segnaposto non creati.");

                return 0;
            }
        }

        $persons = $this->praticaClients($codes)->where('is_person', true)->where('is_dummy', false)->get();

        if (! $this->option('dry-run')) {
            // Il documento segnaposto della prima versione si chiamava "(segnaposto)".
            Document::query()
                ->where('documentable_type', (new Client)->getMorphClass())
                ->where('name', "Carta d'Identità (segnaposto)")
                ->update(['name' => "Carta d'Identità"]);
        }

        $existing = Document::query()
            ->where('documentable_type', (new Client)->getMorphClass())
            ->whereIn('documentable_id', $persons->modelKeys())
            ->whereIn('name', array_keys(self::PLACEHOLDER_DOCUMENTS))
            ->get(['documentable_id', 'name'])
            ->groupBy(fn (Document $document) => (int) $document->documentable_id)
            ->map(fn ($documents) => $documents->pluck('name')->all());

        $created = 0;

        foreach ($persons as $client) {
            foreach (self::PLACEHOLDER_DOCUMENTS as $name => $definition) {
                if (in_array($name, $existing->get($client->getKey(), []), true)) {
                    continue;
                }

                if (! $this->option('dry-run')) {
                    $client->documents()->create([
                        'document_type_id' => $types[$definition['type']]->getKey(),
                        'name' => $name,
                        'docnumber' => $definition['prefix'].str_pad((string) $client->getKey(), 7, '0', STR_PAD_LEFT),
                        'emitted_by' => $definition['emitted_by'],
                        'emitted_at' => $definition['emitted_at'],
                        'status' => DocumentStatus::UPLOADED->value,
                    ]);
                }

                $created++;
            }
        }

        return $created;
    }

    /**
     * Importo, numero rate e banca segnaposto sulle pratiche che non li hanno.
     *
     * @param  Collection<int, string>  $codes
     */
    private function fillPlaceholderPraticaData($codes): int
    {
        $query = Pratica::query()
            ->whereIn('codice_fiscale', $codes)
            ->where(fn ($query) => $query->whereNull('amount')->orWhereNull('nrate')
                ->orWhereNull('denominazione_banca')->orWhere('denominazione_banca', ''));

        $count = (clone $query)->count();

        if (! $this->option('dry-run')) {
            (clone $query)->whereNull('amount')->update(['amount' => self::DEFAULT_AMOUNT]);
            (clone $query)->whereNull('nrate')->update(['nrate' => self::DEFAULT_INSTALMENTS]);
            (clone $query)->where(fn ($q) => $q->whereNull('denominazione_banca')->orWhere('denominazione_banca', ''))
                ->update(['denominazione_banca' => self::DEFAULT_BANK]);
        }

        return $count;
    }

    /**
     * @param  Collection<int, string>  $codes
     * @return Builder<Client>
     */
    private function praticaClients($codes)
    {
        return Client::query()
            ->where(fn ($query) => $query->whereIn('tax_code', $codes)->orWhereIn('vat_number', $this->vatCodes($codes)));
    }

    /**
     * PEC, codice Ateco e iscrizione CCIAA segnaposto per le societa' delle pratiche.
     *
     * @param  Collection<int, string>  $codes
     */
    private function fillPlaceholderCompanyData($codes): int
    {
        $count = 0;

        $this->praticaClients($codes)
            ->where('is_person', false)
            ->where(fn ($query) => $query->whereNull('pec')->orWhereNull('ateco_code')->orWhereNull('cciaa_registration'))
            ->lazyById()
            ->each(function (Client $company) use (&$count): void {
                if (! $this->option('dry-run')) {
                    $company->update([
                        'pec' => $company->pec ?? 'societa'.$company->getKey().'@'.self::DEFAULT_PEC_DOMAIN,
                        'ateco_code' => $company->ateco_code ?? self::DEFAULT_ATECO,
                        'cciaa_registration' => $company->cciaa_registration ?? 'RM-'.str_pad((string) $company->getKey(), 7, '0', STR_PAD_LEFT),
                    ]);
                }

                $count++;
            });

        return $count;
    }

    /**
     * Crea i fornitori (collaboratori) che le pratiche indicano con la partita IVA dell'agente
     * ma che non esistono: il nome viene dalla denominazione dell'agente nella pratica.
     */
    private function createMissingSuppliers(): int
    {
        $existing = Fornitore::withTrashed()->whereNotNull('piva')->pluck('piva')->map(fn ($vat) => (string) $vat)->flip();

        $missing = Pratica::query()
            ->whereNotNull('partita_iva_agente')
            ->where('partita_iva_agente', '!=', '')
            ->get(['partita_iva_agente', 'denominazione_agente'])
            ->unique(fn (Pratica $pratica) => (string) $pratica->partita_iva_agente)
            ->reject(fn (Pratica $pratica) => $existing->has((string) $pratica->partita_iva_agente));

        if (! $this->option('dry-run')) {
            foreach ($missing as $pratica) {
                Fornitore::create([
                    'name' => filled($pratica->denominazione_agente) ? $pratica->denominazione_agente : 'Agente '.$pratica->partita_iva_agente,
                    'piva' => (string) $pratica->partita_iva_agente,
                ]);
            }
        }

        return $missing->count();
    }

    /**
     * Contatti e indirizzo segnaposto dei fornitori (collaboratori) collegati alle pratiche
     * tramite la partita IVA dell'agente. Completa solo i campi vuoti.
     */
    private function fillPlaceholderSupplierData(): int
    {
        $vatNumbers = Pratica::query()
            ->whereNotNull('partita_iva_agente')
            ->where('partita_iva_agente', '!=', '')
            ->distinct()
            ->pluck('partita_iva_agente')
            ->map(fn ($vat) => (string) $vat);

        $defaults = [
            'tel' => '0612345678',
            'indirizzo' => 'Via del Collaboratore 10',
            'comune' => 'Roma',
            'cap' => '00100',
            'prov' => 'RM',
            'cf' => 'ZZAGNT80A01H501Q',
        ];

        $count = 0;

        Fornitore::query()
            ->whereIn('piva', $vatNumbers)
            ->lazyById(100, 'id')
            ->each(function (Fornitore $supplier) use ($defaults, &$count): void {
                $updates = [];

                foreach ($defaults as $column => $value) {
                    if (blank($supplier->{$column})) {
                        $updates[$column] = $value;
                    }
                }

                if (blank($supplier->email)) {
                    $updates['email'] = 'collaboratore-'.substr((string) $supplier->getKey(), 0, 8).'@example.test';
                }

                if ($updates === []) {
                    return;
                }

                if (! $this->option('dry-run')) {
                    $supplier->update($updates);
                }

                $count++;
            });

        return $count;
    }

    /**
     * Una pratica "non nostra" (finanziamento di terzi) per ogni persona fisica delle pratiche
     * che non ne ha una: e' il dato da cui parte la richiesta di conteggio estintivo.
     *
     * @param  Collection<int, string>  $codes
     * @param  Collection<string, Collection<int, Pratica>>  $groups
     */
    private function createPlaceholderThirdPartyFinancings($codes, $groups): int
    {
        $withThirdParty = Pratica::query()
            ->whereIn('codice_fiscale', $codes)
            ->where('is_notowned', true)
            ->pluck('codice_fiscale')
            ->map(fn ($code) => Str::upper(trim((string) $code)))
            ->flip();

        $count = 0;

        $this->praticaClients($codes)
            ->where('is_person', true)
            ->where('is_dummy', false)
            ->lazyById()
            ->each(function (Client $client) use ($groups, $withThirdParty, &$count): void {
                $code = Str::upper(trim((string) $client->tax_code));

                if ($withThirdParty->has($code) || ! $groups->has($code)) {
                    return;
                }

                if (! $this->option('dry-run')) {
                    $base = $groups->get($code)->first();

                    Pratica::create([
                        'id' => (string) Str::uuid(),
                        'codice_pratica' => 'TERZI-'.$client->getKey(),
                        'nome_cliente' => $base->nome_cliente,
                        'cognome_cliente' => $base->cognome_cliente,
                        'codice_fiscale' => $code,
                        'denominazione_agente' => $base->denominazione_agente,
                        'partita_iva_agente' => $base->partita_iva_agente,
                        'denominazione_banca' => self::DEFAULT_THIRD_PARTY_BANK,
                        'tipo_prodotto' => 'Cessione',
                        'denominazione_prodotto' => self::DEFAULT_THIRD_PARTY_PRODUCT,
                        'data_inserimento_pratica' => '2020-01-01',
                        'stato_pratica' => 'PERFEZIONATA',
                        'rata' => self::DEFAULT_THIRD_PARTY_INSTALMENT,
                        'nrate' => self::DEFAULT_INSTALMENTS,
                        'amount' => self::DEFAULT_THIRD_PARTY_INSTALMENT * self::DEFAULT_INSTALMENTS,
                        'is_notowned' => true,
                    ]);
                }

                $count++;
            });

        return $count;
    }

    /**
     * Le pratiche senza codice fiscale/P.IVA ricevono un codice finto univoco (uguale per lo
     * stesso nominativo), cosi' si collegano a un cliente: 16 caratteri decodificabili se c'e' un
     * nome (persona), 11 cifre che iniziano per 9 altrimenti (societa'). Con `--code` il passo
     * riguarda solo le pratiche indicate con `--pratica`.
     */
    private function assignPlaceholderTaxCodes(): int
    {
        $query = Pratica::query()
            ->where('is_notowned', false)
            ->where(fn ($query) => $query->whereNull('codice_fiscale')->orWhere('codice_fiscale', ''))
            // Senza nome ne' cognome non c'e' nulla da cui creare un cliente.
            ->where(fn ($query) => $query->whereRaw("TRIM(COALESCE(nome_cliente, '')) != ''")->orWhereRaw("TRIM(COALESCE(cognome_cliente, '')) != ''"));

        if ($this->option('code') !== []) {
            if ($this->option('pratica') === []) {
                return 0;
            }

            $query->whereIn('codice_pratica', $this->option('pratica'));
        }

        $pratiche = $query->get(['id', 'codice_pratica', 'nome_cliente', 'cognome_cliente']);

        if ($pratiche->isEmpty()) {
            return 0;
        }

        $used = Pratica::query()->whereNotNull('codice_fiscale')->pluck('codice_fiscale')
            ->merge(Client::query()->pluck('tax_code'))->merge(Client::query()->pluck('vat_number'))
            ->filter()
            ->map(fn ($code) => Str::upper(trim((string) $code)))
            ->flip();

        $personCounter = 0;
        $companyCounter = 0;
        $byName = [];

        foreach ($pratiche as $pratica) {
            $nameKey = Str::upper(trim($pratica->cognome_cliente.' '.$pratica->nome_cliente));
            $isCompany = trim((string) $pratica->nome_cliente) === '';

            $byName[$nameKey] ??= $isCompany
                ? $this->nextCode($used, $companyCounter, fn (int $n) => '9'.str_pad((string) $n, 10, '0', STR_PAD_LEFT))
                : $this->nextCode($used, $personCounter, fn (int $n) => 'ZZ'.$this->letters($n).'80A01H501Z');

            if (! $this->option('dry-run')) {
                Pratica::whereKey($pratica->getKey())->update(['codice_fiscale' => $byName[$nameKey]]);
            }
        }

        return $pratiche->count();
    }

    /**
     * @param  Collection<string, int>  $used
     * @param  callable(int): string  $format
     */
    private function nextCode($used, int &$counter, callable $format): string
    {
        do {
            $code = $format(++$counter);
        } while ($used->has($code));

        $used->put($code, 0);

        return $code;
    }

    private function letters(int $number): string
    {
        $letters = '';

        for ($i = 0; $i < 4; $i++) {
            $letters = chr(65 + $number % 26).$letters;
            $number = intdiv($number, 26);
        }

        return $letters;
    }

    /**
     * Le "persone" senza nome (nella pratica il cognome contiene la ragione sociale) sono societa':
     * si riclassificano e si tolgono i dati da persona fisica segnaposto (residenza, documenti,
     * nascita, cittadinanza, stipendio); il domicilio diventa la sede principale.
     *
     * @param  Collection<int, string>  $codes
     */
    private function reclassifyNamelessPersons($codes): int
    {
        $clients = $this->praticaClients($codes)
            ->where('is_person', true)
            ->where('is_dummy', false)
            ->where(fn ($query) => $query->whereNull('first_name')->orWhere('first_name', ''))
            ->get();

        if (! $this->option('dry-run')) {
            foreach ($clients as $client) {
                $client->update([
                    'is_person' => false,
                    'is_company' => true,
                    'birth_date' => null,
                    'birth_place' => null,
                    'sex' => null,
                    'citizenship' => null,
                    'salary' => null,
                ]);

                $branches = Branch::query()->where('branchable_type', (new Client)->getMorphClass())->where('branchable_id', $client->getKey());

                (clone $branches)->where('name', 'Residenza')->where('address', 'da inserire')->delete();
                (clone $branches)->where('name', 'Domicilio')->update(['is_main_office' => true]);

                Document::query()
                    ->where('documentable_type', (new Client)->getMorphClass())
                    ->where('documentable_id', $client->getKey())
                    ->whereIn('name', array_keys(self::PLACEHOLDER_DOCUMENTS))
                    ->delete();
            }
        }

        return $clients->count();
    }

    private function companyId(): string
    {
        return $this->companyId ??= app(CompanyResolver::class)->resolveId();
    }

    /**
     * Una P.IVA (11 cifre) identifica una societa': la ragione sociale sta nel cognome della pratica.
     * Come per il form cliente, per le societa' `tax_code` contiene la P.IVA.
     *
     * @return array<string, mixed>|null null se la pratica non ha ne' nome ne' cognome
     */
    private function attributesFor(string $code, Pratica $pratica): ?array
    {
        $surname = trim((string) $pratica->cognome_cliente);
        $firstName = trim((string) $pratica->nome_cliente);
        $isVat = (bool) preg_match('/^\d{11}$/', $code);
        // Senza nome non e' una persona fisica: la ragione sociale sta nel cognome della pratica.
        $isCompany = $isVat || $firstName === '';

        $name = $isCompany ? trim($surname.' '.$firstName) : $surname;

        if ($name === '') {
            return null;
        }

        return [
            'name' => $name,
            'first_name' => $isCompany || $firstName === '' ? null : $firstName,
            'tax_code' => $code,
            'vat_number' => $isVat ? $code : null,
            'is_person' => ! $isCompany,
            'is_company' => $isCompany,
            'is_client' => true,
            'iban' => self::DEFAULT_IBAN,
            'salary' => $isCompany ? null : self::DEFAULT_SALARY,
        ];
    }
}
