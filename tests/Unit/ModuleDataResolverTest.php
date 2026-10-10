<?php

namespace Tests\Unit;

use App\Enums\ModuleSourceKey as Key;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\PROFORMA\Fornitore;
use App\Models\PROFORMA\Pratica;
use App\Models\PROFORMA\Provvigione;
use App\Services\ModuleDataResolver;
use Carbon\Carbon;
use Tests\TestCase;

class ModuleDataResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-06 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function pratica(array $attributes = [], ?Fornitore $agent = null): Pratica
    {
        $pratica = new Pratica(array_merge([
            'id' => 'pratica-1',
            'codice_pratica' => 'P-2026-001',
            'codice_fiscale' => 'RSSMRA80A01H501U',
            'tipo_prodotto' => 'Prestito',
            'denominazione_banca' => 'Banca Test',
            'abi' => '03069',
            'denominazione_prodotto' => 'Prestito Personale',
            'data_inserimento_pratica' => '2026-09-01',
            'amount' => 15000,
            'rata' => 250.5,
            'nrate' => 72,
        ], $attributes));

        return $pratica->setRelation('agente', $agent);
    }

    /**
     * @param  array<int, Branch>  $branches
     * @param  array<int, Document>  $documents
     */
    private function client(array $attributes = [], array $branches = [], array $documents = [], ?Client $employer = null): Client
    {
        $rep = $attributes['_rep'] ?? null;
        $third = $attributes['_third'] ?? [];
        unset($attributes['_rep'], $attributes['_third']);

        $client = new Client(array_merge([
            'name' => 'Rossi',
            'first_name' => 'Mario',
            'tax_code' => 'RSSMRA80A01H501U',
            'vat_number' => null,
            'email' => 'mario@example.test',
            'phone' => '3331234567',
            'is_person' => true,
            'salary' => 1800,
            'iban' => 'IT60X0542811101000000123456',
        ], $attributes));

        $client->setRelation('branches', collect($branches));
        $client->setRelation('documents', collect($documents));
        $client->setRelation('employer', $employer);
        $client->setRelation('legalRepresentative', $rep);
        $client->setRelation('thirdPartyFinancings', collect($third));

        return $client;
    }

    private function branch(array $attributes = [], ?string $createdAt = null): Branch
    {
        $branch = (new Branch)->forceFill(array_merge([
            'address' => 'Via Roma',
            'street_number' => '10',
            'city' => 'Napoli',
            'zip_code' => '80100',
            'province' => 'NA',
            'is_main_office' => true,
            'dismissed_at' => null,
        ], $attributes));

        $branch->created_at = Carbon::parse($createdAt ?? '2026-01-01');

        return $branch;
    }

    private function identity(array $attributes = [], string $code = 'CARTA_IDENTITA', string $typeName = "Carta d'Identità"): Document
    {
        $document = (new Document)->forceFill(array_merge([
            'docnumber' => 'AY1234567',
            'emitted_by' => 'Comune di Napoli',
            'emitted_at' => '2022-05-01',
            'expires_at' => '2032-05-01',
        ], $attributes));

        $document->setRelation('documentType', (new DocumentType)->forceFill(['code' => $code, 'name' => $typeName]));

        return $document;
    }

    private function resolve(Pratica $pratica, Client $client)
    {
        return (new ModuleDataResolver)->resolve($pratica, $client);
    }

    public function test_resolves_pratica_and_client_values(): void
    {
        $data = $this->resolve($this->pratica(), $this->client());

        $this->assertSame('P-2026-001', $data->get(Key::PraticaCodice));
        $this->assertEquals(15000, $data->get(Key::PraticaImporto));
        $this->assertSame(72, $data->get(Key::PraticaNumeroRate));
        $this->assertSame('Banca Test', $data->get(Key::PraticaBanca));
        $this->assertSame('03069', $data->get(Key::PraticaAbi));
        $this->assertSame('2026-10-06', $data->get(Key::PraticaOggi)->toDateString());
        $this->assertSame('2026-09-01', $data->get(Key::PraticaDataInserimento)->toDateString());

        $this->assertSame('Rossi', $data->get(Key::ClienteCognome));
        $this->assertSame('Mario', $data->get(Key::ClienteNome));
        $this->assertSame('Rossi Mario', $data->get(Key::ClienteNominativo));
        $this->assertSame('RSSMRA80A01H501U', $data->get(Key::ClienteCodiceFiscale));
        $this->assertSame('IT60X0542811101000000123456', $data->get(Key::ClienteIban));
        $this->assertTrue($data->get(Key::ClientePersonaFisica));
    }

    public function test_sums_only_active_client_compensations_and_picks_the_proforma_number(): void
    {
        $pratica = $this->pratica()->setRelation('provvigioni', collect([
            new Provvigione(['tipo' => 'Cliente', 'importo' => 1000, 'annullato' => false, 'n_fattura' => '147']),
            new Provvigione(['tipo' => 'Cliente', 'importo' => 500.5, 'annullato' => false, 'n_fattura' => '']),
            new Provvigione(['tipo' => 'Cliente', 'importo' => 300, 'annullato' => true, 'n_fattura' => '999']),
            new Provvigione(['tipo' => 'Agente', 'importo' => 700, 'annullato' => false, 'n_fattura' => '1']),
        ]));

        $data = $this->resolve($pratica, $this->client());

        $this->assertEquals(1500.5, $data->get(Key::CompensoClienteImporto));
        $this->assertSame('147', $data->get(Key::CompensoClienteProforma));
    }

    public function test_client_compensation_is_empty_without_client_commissions(): void
    {
        $data = $this->resolve($this->pratica()->setRelation('provvigioni', collect()), $this->client());

        $this->assertNull($data->get(Key::CompensoClienteImporto));
        $this->assertNull($data->get(Key::CompensoClienteProforma));
    }

    public function test_resolves_birth_data_and_legal_representative(): void
    {
        $rep = new Client(['name' => 'Amministratore di Acme Spa', 'is_person' => true]);

        $client = $this->client(['birth_date' => '1980-01-01', 'birth_place' => 'Roma (RM)', 'sex' => 'M', 'citizenship' => 'Italiana', '_rep' => $rep]);

        $data = $this->resolve($this->pratica(), $client);

        $this->assertSame('1980-01-01', $data->get(Key::ClienteDataNascita)->toDateString());
        $this->assertSame('Roma (RM)', $data->get(Key::ClienteLuogoNascita));
        $this->assertSame('M', $data->get(Key::ClienteSesso));
        $this->assertSame('Italiana', $data->get(Key::ClienteCittadinanza));
        $this->assertSame('Amministratore di Acme Spa', $data->get(Key::RappresentanteNominativo));
        $this->assertFalse($this->resolve($this->pratica(), $this->client())->has(Key::RappresentanteNominativo));
    }

    public function test_resolves_agent_third_party_financing_and_company_data(): void
    {
        $agent = (new Fornitore)->forceFill(['name' => 'Agenzia Verdi', 'indirizzo' => 'Via Milano 5', 'cap' => '20100', 'comune' => 'Milano', 'prov' => 'MI', 'email' => 'verdi@example.test', 'tel' => '021234567', 'cf' => 'VRDGNN70A01F205X']);
        $third = new Pratica(['denominazione_banca' => 'Finanziaria Terza', 'denominazione_prodotto' => 'Cessione del quinto', 'rata' => 300, 'is_notowned' => true]);
        $rep = new Client(['name' => 'Amministratore di Acme Spa', 'email' => 'rep@example.test', 'phone' => '3330000000']);
        $client = $this->client(['is_person' => false, 'pec' => 'acme@pec.example.test', 'ateco_code' => '62.01.00', 'cciaa_registration' => 'RM-123', '_rep' => $rep, '_third' => [$third]]);

        $data = $this->resolve($this->pratica(agent: $agent), $client);

        $this->assertSame('Agenzia Verdi', $data->get(Key::AgenteNominativo));
        $this->assertSame('Via Milano 5, 20100 Milano (MI)', $data->get(Key::AgenteIndirizzo));
        $this->assertSame('verdi@example.test', $data->get(Key::AgenteEmail));
        $this->assertSame('021234567', $data->get(Key::AgenteTelefono));
        $this->assertSame('VRDGNN70A01F205X', $data->get(Key::AgenteCodiceFiscale));

        $this->assertSame('Finanziaria Terza', $data->get(Key::TerziBanca));
        $this->assertSame('Cessione del quinto', $data->get(Key::TerziProdotto));
        $this->assertEquals(300, $data->get(Key::TerziRata));

        $this->assertSame('acme@pec.example.test', $data->get(Key::ClientePec));
        $this->assertSame('62.01.00', $data->get(Key::ClienteAteco));
        $this->assertSame('RM-123', $data->get(Key::ClienteCciaa));
        $this->assertSame('rep@example.test', $data->get(Key::RappresentanteEmail));
        $this->assertSame('3330000000', $data->get(Key::RappresentanteTelefono));

        $empty = $this->resolve($this->pratica(), $this->client());
        $this->assertFalse($empty->has(Key::AgenteNominativo));
        $this->assertFalse($empty->has(Key::TerziBanca));
    }

    public function test_company_nominativo_is_just_the_business_name(): void
    {
        $data = $this->resolve($this->pratica(), $this->client(['name' => 'Acme Spa', 'first_name' => null, 'is_person' => false]));

        $this->assertSame('Acme Spa', $data->get(Key::ClienteNominativo));
        $this->assertFalse($data->get(Key::ClientePersonaFisica));
    }

    public function test_main_branch_is_the_most_recent_flagged_one_and_builds_the_full_address(): void
    {
        $old = $this->branch(['address' => 'Via Vecchia', 'city' => 'Roma', 'zip_code' => '00100', 'province' => 'RM'], '2020-01-01');
        $recent = $this->branch([], '2026-03-01');

        $data = $this->resolve($this->pratica(), $this->client(branches: [$old, $recent]));

        $this->assertSame('Via Roma', $data->get(Key::SedeIndirizzo));
        $this->assertSame('10', $data->get(Key::SedeCivico));
        $this->assertSame('Via Roma 10, 80100 Napoli (NA)', $data->get(Key::SedeIndirizzoCompleto));
    }

    public function test_a_single_branch_not_flagged_as_main_office_is_used_as_fallback(): void
    {
        $branch = $this->branch(['is_main_office' => false]);

        $data = $this->resolve($this->pratica(), $this->client(branches: [$branch]));

        $this->assertSame('Via Roma 10, 80100 Napoli (NA)', $data->get(Key::SedeIndirizzoCompleto));
    }

    public function test_dismissed_branches_are_ignored(): void
    {
        $dismissed = $this->branch(['dismissed_at' => '2025-01-01']);

        $data = $this->resolve($this->pratica(), $this->client(branches: [$dismissed]));

        $this->assertFalse($data->has(Key::SedeIndirizzo));
        $this->assertContains(Key::SedeIndirizzoCompleto, $data->missingAmong([Key::SedeIndirizzoCompleto]));
    }

    public function test_identity_document_prefers_a_valid_recent_one_and_skips_expired_and_other_types(): void
    {
        $expired = $this->identity(['docnumber' => 'SCADUTO', 'expires_at' => '2025-01-01', 'emitted_at' => '2015-01-01']);
        $valid = $this->identity(['docnumber' => 'VALIDO', 'emitted_at' => '2023-01-01', 'expires_at' => '2033-01-01']);
        $other = $this->identity(['docnumber' => 'ALTRO'], 'BUSTA_PAGA', 'Busta paga');

        $data = $this->resolve($this->pratica(), $this->client(documents: [$expired, $other, $valid]));

        $this->assertSame('VALIDO', $data->get(Key::DocumentoNumero));
        $this->assertSame("Carta d'Identità", $data->get(Key::DocumentoTipo));
        $this->assertSame('Comune di Napoli', $data->get(Key::DocumentoRilasciatoDa));
        $this->assertSame('2023-01-01', $data->get(Key::DocumentoRilasciatoIl)->toDateString());
        $this->assertSame('2033-01-01', $data->get(Key::DocumentoScadenza)->toDateString());
    }

    public function test_driving_licence_is_accepted_by_type_name(): void
    {
        $licence = $this->identity(['docnumber' => 'PAT-1'], 'PATENTE', 'Patente di Guida');

        $data = $this->resolve($this->pratica(), $this->client(documents: [$licence]));

        $this->assertSame('PAT-1', $data->get(Key::DocumentoNumero));
    }

    public function test_the_combined_identity_document_type_used_by_the_app_is_accepted(): void
    {
        $document = $this->identity(['docnumber' => 'CIE-1'], 'ALTRO', 'Carta di Identità o Patente & cert. Residenza');

        $data = $this->resolve($this->pratica(), $this->client(documents: [$document]));

        $this->assertSame('CIE-1', $data->get(Key::DocumentoNumero));
    }

    public function test_a_residence_certificate_sharing_the_identity_type_is_not_used_as_identity_document(): void
    {
        $name = 'Carta di Identità o Patente & cert. Residenza';
        $certificate = $this->identity(['docnumber' => 'CR-1', 'name' => 'Certificato di Residenza', 'emitted_at' => '2026-01-01'], 'ALTRO', $name);
        $card = $this->identity(['docnumber' => 'CI-1', 'name' => "Carta d'Identità", 'emitted_at' => '2023-01-01'], 'ALTRO', $name);

        $data = $this->resolve($this->pratica(), $this->client(documents: [$certificate, $card]));

        $this->assertSame('CI-1', $data->get(Key::DocumentoNumero));
    }

    public function test_only_expired_identity_document_yields_no_values_and_is_reported_missing(): void
    {
        $expired = $this->identity(['expires_at' => '2025-01-01']);

        $data = $this->resolve($this->pratica(), $this->client(documents: [$expired]));

        $this->assertFalse($data->has(Key::DocumentoNumero));
        $this->assertSame(
            [Key::DocumentoNumero, Key::DocumentoScadenza],
            $data->missingAmong([Key::DocumentoNumero, Key::ClienteNome, Key::DocumentoScadenza]),
        );
    }

    public function test_employer_values_come_from_the_employer_and_its_main_branch(): void
    {
        $employer = $this->client(
            ['name' => 'Acme Spa', 'first_name' => null, 'is_person' => false, 'vat_number' => '01234567890'],
            [$this->branch(['address' => 'Corso Italia', 'street_number' => '5', 'city' => 'Milano', 'zip_code' => '20100', 'province' => 'MI'])],
        );

        $data = $this->resolve($this->pratica(), $this->client(employer: $employer));

        $this->assertSame('Acme Spa', $data->get(Key::DatoreNome));
        $this->assertSame('01234567890', $data->get(Key::DatorePartitaIva));
        $this->assertSame('Corso Italia 5, 20100 Milano (MI)', $data->get(Key::DatoreIndirizzo));
    }

    public function test_missing_values_are_reported_but_false_and_zero_count_as_present(): void
    {
        $client = $this->client(['is_person' => false, 'salary' => 0, 'iban' => null, 'email' => '']);

        $data = $this->resolve($this->pratica(['rata' => null]), $client);

        $this->assertTrue($data->has(Key::ClientePersonaFisica));
        $this->assertFalse($data->isTruthy(Key::ClientePersonaFisica));
        $this->assertTrue($data->has(Key::ClienteStipendio));
        $this->assertSame(
            [Key::ClienteIban, Key::ClienteEmail, Key::PraticaRata, Key::DatoreNome],
            $data->missingAmong([Key::ClienteIban, Key::ClienteEmail, Key::PraticaRata, Key::DatoreNome, Key::ClienteNome]),
        );
    }
}
