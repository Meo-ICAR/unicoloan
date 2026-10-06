<?php

namespace Tests\Unit;

use App\Enums\ModuleSourceKey as Key;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\PROFORMA\Pratica;
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

    private function pratica(array $attributes = []): Pratica
    {
        return new Pratica(array_merge([
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
    }

    /**
     * @param  array<int, Branch>  $branches
     * @param  array<int, Document>  $documents
     */
    private function client(array $attributes = [], array $branches = [], array $documents = [], ?Client $employer = null): Client
    {
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
