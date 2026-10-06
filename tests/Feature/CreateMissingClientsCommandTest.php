<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\DocumentType;
use App\Models\PROFORMA\Fornitore;
use App\Models\PROFORMA\Pratica;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class CreateMissingClientsCommandTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Le sedi richiedono una Company (FK): nel DB di test non ce n'e' nessuna.
        Company::first() ?? Company::factory()->create();
        DocumentType::where('name', 'Carta di Identità o Patente & cert. Residenza')->first()
            ?? DocumentType::create(['name' => 'Carta di Identità o Patente & cert. Residenza']);
        DocumentType::where('name', 'Patente di Guida')->first() ?? DocumentType::create(['name' => 'Patente di Guida']);
    }

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = ['mysql', 'mysql_proforma'];

    /**
     * Il DB di sviluppo contiene pratiche reali: il comando lavora solo sui codici del test.
     *
     * @var array<int, string>
     */
    private const CODES = ['ZZTEST80A01H501A', '09999999991', 'ZZTEST85M41F839B', '09999999992', 'ZZTEST70A01H501C', 'ZZTEST90A01H501D'];

    private function pratica(array $attributes): Pratica
    {
        return Pratica::create(array_merge(['id' => (string) Str::uuid()], $attributes));
    }

    public function test_creates_a_person_from_the_most_recent_pratica(): void
    {
        $this->pratica(['codice_fiscale' => 'ZZTEST80A01H501A', 'nome_cliente' => 'MARIO VECCHIO', 'cognome_cliente' => 'ROSSI', 'data_inserimento_pratica' => '2020-01-01']);
        $this->pratica(['codice_fiscale' => 'zztest80a01h501a ', 'nome_cliente' => 'MARIO', 'cognome_cliente' => 'ROSSI', 'data_inserimento_pratica' => '2025-01-01']);

        $this->artisan('clients:create-missing', ['--code' => self::CODES])->expectsOutputToContain('Creati: 1 clienti')->assertExitCode(0);

        $client = Client::where('tax_code', 'ZZTEST80A01H501A')->sole();
        $this->assertSame('ROSSI', $client->name);
        $this->assertSame('MARIO', $client->first_name);
        $this->assertTrue($client->is_person);
        $this->assertTrue($client->is_client);
    }

    public function test_creates_placeholder_branches_for_new_and_existing_clients_without_any(): void
    {
        $this->pratica(['codice_fiscale' => 'ZZTEST80A01H501A', 'cognome_cliente' => 'ROSSI', 'nome_cliente' => 'MARIO']);
        $this->pratica(['codice_fiscale' => '09999999991', 'cognome_cliente' => 'ZZ TEST SRL']);
        $this->pratica(['codice_fiscale' => 'ZZTEST85M41F839B', 'cognome_cliente' => 'BIANCHI', 'nome_cliente' => 'LAURA']);
        $withBranch = Client::create(['name' => 'BIANCHI', 'is_person' => true, 'first_name' => 'NOME', 'tax_code' => 'ZZTEST85M41F839B']);
        $withBranch->branches()->create(['company_id' => Company::first()->id, 'name' => 'Casa', 'address' => 'Via Vera 1']);
        $existing = Client::create(['name' => 'NERI', 'is_person' => true, 'first_name' => 'NOME', 'tax_code' => 'ZZTEST90A01H501D']);
        $this->pratica(['codice_fiscale' => 'ZZTEST90A01H501D', 'cognome_cliente' => 'NERI', 'nome_cliente' => 'MARCO']);

        $this->artisan('clients:create-missing', ['--code' => self::CODES])->assertExitCode(0);

        $person = Client::where('tax_code', 'ZZTEST80A01H501A')->sole()->branches->keyBy('name');
        $this->assertSame(['Domicilio', 'Residenza'], $person->keys()->sort()->values()->all());
        $this->assertSame(['da inserire', '00100', 'Roma', false], [$person['Domicilio']->address, $person['Domicilio']->zip_code, $person['Domicilio']->city, $person['Domicilio']->is_main_office]);
        $this->assertSame(['da inserire', '80100', 'Napoli', true], [$person['Residenza']->address, $person['Residenza']->zip_code, $person['Residenza']->city, $person['Residenza']->is_main_office]);

        $company = Client::where('tax_code', '09999999991')->sole()->branches;
        $this->assertSame(['Domicilio'], $company->pluck('name')->all());
        $this->assertTrue($company->first()->is_main_office);

        $this->assertSame(['Casa'], $withBranch->branches()->pluck('name')->all());
        $this->assertSame(['Domicilio', 'Residenza'], $existing->branches()->orderBy('name')->pluck('name')->all());

        $this->artisan('clients:create-missing', ['--code' => self::CODES])->assertExitCode(0);
        $this->assertSame(2, $existing->branches()->count());
    }

    public function test_forces_placeholder_iban_and_salary_on_pratica_clients(): void
    {
        $this->pratica(['codice_fiscale' => 'ZZTEST80A01H501A', 'cognome_cliente' => 'ROSSI', 'nome_cliente' => 'MARIO']);
        $this->pratica(['codice_fiscale' => '09999999991', 'cognome_cliente' => 'ZZ TEST SRL']);
        $this->pratica(['codice_fiscale' => 'ZZTEST85M41F839B', 'cognome_cliente' => 'BIANCHI', 'nome_cliente' => 'LAURA']);
        Client::create(['name' => 'BIANCHI', 'is_person' => true, 'first_name' => 'NOME', 'tax_code' => 'ZZTEST85M41F839B', 'iban' => 'IT00VECCHIO', 'salary' => 2500]);
        Client::create(['name' => 'ESTRANEO', 'is_person' => true, 'first_name' => 'NOME', 'tax_code' => 'ZZTEST99A01H501E', 'iban' => 'IT00ESTRANEO', 'salary' => 1]);

        $this->artisan('clients:create-missing', ['--code' => self::CODES])->assertExitCode(0);

        $person = Client::where('tax_code', 'ZZTEST80A01H501A')->sole();
        $this->assertSame('IT1234567890123456', $person->iban);
        $this->assertEquals(999, $person->salary);

        $company = Client::where('tax_code', '09999999991')->sole();
        $this->assertSame('IT1234567890123456', $company->iban);
        $this->assertNull($company->salary);

        $existing = Client::where('tax_code', 'ZZTEST85M41F839B')->sole();
        $this->assertSame('IT1234567890123456', $existing->iban);
        $this->assertEquals(999, $existing->salary);

        $unrelated = Client::where('tax_code', 'ZZTEST99A01H501E')->sole();
        $this->assertSame('IT00ESTRANEO', $unrelated->iban);

        $this->artisan('clients:create-missing', ['--dry-run' => true, '--code' => self::CODES])
            ->expectsOutputToContain('[dry-run] Clienti con IBAN/stipendio da forzare: 0')
            ->assertExitCode(0);
    }

    public function test_fills_birth_data_from_the_tax_code_and_forces_italian_citizenship(): void
    {
        $this->pratica(['codice_fiscale' => 'ZZTEST80A01H501A', 'cognome_cliente' => 'ROSSI', 'nome_cliente' => 'MARIO']);
        Client::create(['name' => 'BIANCHI', 'is_person' => true, 'first_name' => 'NOME', 'tax_code' => 'BNCLRA85M41F839X']);
        Client::create(['name' => 'GIA COMPLETA', 'is_person' => true, 'first_name' => 'NOME', 'tax_code' => 'RSSMRA80A01H501U', 'birth_place' => 'Milano (MI)', 'citizenship' => 'Francese']);
        Client::create(['name' => 'CF BRUTTO', 'is_person' => true, 'first_name' => 'NOME', 'tax_code' => 'NONVALIDO']);

        $this->artisan('clients:create-missing', ['--code' => self::CODES])->assertExitCode(0);

        $rossi = Client::where('tax_code', 'ZZTEST80A01H501A')->sole();
        $this->assertSame('1980-01-01', $rossi->birth_date->toDateString());
        $this->assertSame('Roma (RM)', $rossi->birth_place);
        $this->assertSame('Italiana', $rossi->citizenship);

        $bianchi = Client::where('tax_code', 'BNCLRA85M41F839X')->sole();
        $this->assertSame('1985-08-01', $bianchi->birth_date->toDateString());
        $this->assertSame('Napoli (NA)', $bianchi->birth_place);
        $this->assertSame('F', $bianchi->sex);
        $this->assertSame('Italiana', $bianchi->citizenship);

        $complete = Client::where('tax_code', 'RSSMRA80A01H501U')->sole();
        $this->assertSame('Milano (MI)', $complete->birth_place);
        $this->assertSame('Francese', $complete->citizenship);
        $this->assertSame('1980-01-01', $complete->birth_date->toDateString());
        $this->assertSame('M', $complete->sex);

        $invalid = Client::where('tax_code', 'NONVALIDO')->sole();
        $this->assertSame('1980-01-01', $invalid->birth_date->toDateString());
        $this->assertSame('Roma (RM)', $invalid->birth_place);
        $this->assertSame('M', $invalid->sex);
    }

    public function test_creates_a_dummy_administrator_for_each_company_without_one(): void
    {
        $this->pratica(['codice_fiscale' => '09999999991', 'cognome_cliente' => 'ZZ TEST SRL']);

        $this->artisan('clients:create-missing', ['--code' => self::CODES])->assertExitCode(0);

        $company = Client::where('tax_code', '09999999991')->sole();
        $administrator = $company->legalRepresentative;

        $this->assertSame('Amministratore di ZZ TEST SRL', $administrator->name);
        $this->assertTrue($administrator->is_person);
        $this->assertTrue($administrator->is_dummy);
        $this->assertSame('Italiana', $administrator->citizenship);
        $this->assertNull($administrator->tax_code);

        $this->artisan('clients:create-missing', ['--code' => self::CODES])->assertExitCode(0);

        $this->assertSame(1, Client::where('name', 'Amministratore di ZZ TEST SRL')->count());
    }

    public function test_fills_placeholder_contacts_identity_document_and_pratica_data(): void
    {
        $withPhone = Client::create(['name' => 'CON TELEFONO', 'is_person' => true, 'first_name' => 'NOME', 'tax_code' => 'ZZTEST85M41F839B', 'phone' => '0612345678']);
        $pratica = $this->pratica(['codice_fiscale' => 'ZZTEST80A01H501A', 'cognome_cliente' => 'ROSSI', 'nome_cliente' => 'MARIO', 'amount' => null, 'nrate' => null, 'denominazione_banca' => '']);
        $this->pratica(['codice_fiscale' => 'ZZTEST85M41F839B', 'cognome_cliente' => 'BIANCHI', 'nome_cliente' => 'LAURA', 'amount' => 5000, 'nrate' => 36, 'denominazione_banca' => 'Banca Vera']);

        $this->artisan('clients:create-missing', ['--code' => self::CODES])->assertExitCode(0);

        $rossi = Client::where('tax_code', 'ZZTEST80A01H501A')->sole();
        $this->assertSame('3331234567', $rossi->phone);
        $this->assertSame('cliente'.$rossi->id.'@example.test', $rossi->email);
        $this->assertSame(['1', '1'], $rossi->branches->pluck('street_number')->all());

        $withPhone->refresh();
        $this->assertSame('0612345678', $withPhone->phone);
        $this->assertNotEmpty($withPhone->email);

        $documents = $rossi->documents->keyBy('name');
        $this->assertSame(["Carta d'Identità", 'Certificato di Residenza', 'Patente di Guida'], $documents->keys()->sort()->values()->all());
        $this->assertSame('AA'.str_pad((string) $rossi->id, 7, '0', STR_PAD_LEFT), $documents["Carta d'Identità"]->docnumber);
        $this->assertSame('Comune di Roma', $documents["Carta d'Identità"]->emitted_by);
        $this->assertSame('Patente di Guida', $documents['Patente di Guida']->documentType->name);
        $this->assertSame($documents["Carta d'Identità"]->document_type_id, $documents['Certificato di Residenza']->document_type_id);
        $this->assertSame(3, $withPhone->documents()->count());

        $pratica->refresh();
        $this->assertEquals(10000, $pratica->amount);
        $this->assertSame(72, $pratica->nrate);
        $this->assertSame('Banca Test', $pratica->denominazione_banca);

        $other = Pratica::where('codice_fiscale', 'ZZTEST85M41F839B')->where('is_notowned', false)->sole();
        $this->assertEquals(5000, $other->amount);
        $this->assertSame(36, $other->nrate);
        $this->assertSame('Banca Vera', $other->denominazione_banca);

        $this->artisan('clients:create-missing', ['--code' => self::CODES])->assertExitCode(0);
        $this->assertSame(3, $rossi->documents()->count());
    }

    public function test_fills_company_data_supplier_contacts_and_administrator_contacts(): void
    {
        $this->pratica(['codice_fiscale' => '09999999991', 'cognome_cliente' => 'ZZ TEST SRL', 'partita_iva_agente' => '09999999993']);
        $emptySupplier = Fornitore::create(['name' => 'AGENZIA VUOTA', 'piva' => '09999999993']);
        $fullSupplier = Fornitore::create(['name' => 'AGENZIA PIENA', 'piva' => '09999999994', 'tel' => '0699999999', 'email' => 'piena@example.test', 'indirizzo' => 'Via Vera 1', 'comune' => 'Milano', 'cap' => '20100', 'prov' => 'MI', 'cf' => 'VRDGNN70A01F205X']);
        $this->pratica(['codice_fiscale' => 'ZZTEST80A01H501A', 'cognome_cliente' => 'ROSSI', 'nome_cliente' => 'MARIO', 'partita_iva_agente' => '09999999994']);

        $this->artisan('clients:create-missing', ['--code' => self::CODES])->assertExitCode(0);

        $company = Client::where('tax_code', '09999999991')->sole();
        $this->assertSame('societa'.$company->id.'@pec.example.test', $company->pec);
        $this->assertSame('62.01.00', $company->ateco_code);
        $this->assertSame('RM-'.str_pad((string) $company->id, 7, '0', STR_PAD_LEFT), $company->cciaa_registration);

        $administrator = $company->legalRepresentative;
        $this->assertSame('3331234567', $administrator->phone);
        $this->assertSame('amministratore'.$company->id.'@example.test', $administrator->email);

        $emptySupplier->refresh();
        $this->assertSame(['0612345678', 'Via del Collaboratore 10', 'Roma', '00100', 'RM'], [$emptySupplier->tel, $emptySupplier->indirizzo, $emptySupplier->comune, $emptySupplier->cap, $emptySupplier->prov]);
        $this->assertNotEmpty($emptySupplier->email);
        $this->assertNotEmpty($emptySupplier->cf);

        $fullSupplier->refresh();
        $this->assertSame(['0699999999', 'piena@example.test', 'Via Vera 1', 'VRDGNN70A01F205X'], [$fullSupplier->tel, $fullSupplier->email, $fullSupplier->indirizzo, $fullSupplier->cf]);
    }

    public function test_creates_the_supplier_an_agent_vat_number_points_to_when_it_does_not_exist(): void
    {
        $this->pratica(['codice_fiscale' => 'ZZTEST80A01H501A', 'cognome_cliente' => 'ROSSI', 'nome_cliente' => 'MARIO', 'denominazione_agente' => 'AGENTE NUOVO', 'partita_iva_agente' => '09999999995']);

        $this->artisan('clients:create-missing', ['--code' => self::CODES])->assertExitCode(0);

        $supplier = Fornitore::where('piva', '09999999995')->sole();
        $this->assertSame('AGENTE NUOVO', $supplier->name);
        $this->assertSame('0612345678', $supplier->tel);

        $this->artisan('clients:create-missing', ['--code' => self::CODES])->assertExitCode(0);
        $this->assertSame(1, Fornitore::where('piva', '09999999995')->count());
    }

    public function test_creates_one_third_party_financing_per_person_and_not_for_companies(): void
    {
        $this->pratica(['codice_fiscale' => 'ZZTEST80A01H501A', 'cognome_cliente' => 'ROSSI', 'nome_cliente' => 'MARIO', 'denominazione_agente' => 'AGENZIA', 'partita_iva_agente' => '09999999993']);
        $this->pratica(['codice_fiscale' => '09999999991', 'cognome_cliente' => 'ZZ TEST SRL']);

        $this->artisan('clients:create-missing', ['--code' => self::CODES])->assertExitCode(0);

        $rossi = Client::where('tax_code', 'ZZTEST80A01H501A')->sole();
        $financing = $rossi->thirdPartyFinancings()->sole();

        $this->assertTrue($financing->is_notowned);
        $this->assertSame('Finanziaria Terza S.p.A.', $financing->denominazione_banca);
        $this->assertEquals(300, $financing->rata);
        $this->assertSame(72 * 300, (int) $financing->amount);
        $this->assertSame('09999999993', $financing->partita_iva_agente);

        $this->assertSame(0, Pratica::where('codice_fiscale', '09999999991')->where('is_notowned', true)->count());

        $this->artisan('clients:create-missing', ['--code' => self::CODES])->assertExitCode(0);
        $this->assertSame(1, $rossi->thirdPartyFinancings()->count());
    }

    public function test_a_pratica_without_name_creates_a_company_even_with_a_personal_tax_code(): void
    {
        $this->pratica(['codice_fiscale' => 'ZZTEST80A01H501A', 'cognome_cliente' => 'ZZ DITTA SNC', 'nome_cliente' => '']);

        $this->artisan('clients:create-missing', ['--code' => self::CODES])->assertExitCode(0);

        $client = Client::where('tax_code', 'ZZTEST80A01H501A')->sole();
        $this->assertFalse($client->is_person);
        $this->assertTrue($client->is_company);
        $this->assertNull($client->vat_number);
        $this->assertNull($client->birth_date);
    }

    public function test_nameless_persons_are_turned_into_companies_and_lose_the_personal_placeholders(): void
    {
        $client = Client::create(['name' => 'ZZ SOCIETA', 'is_person' => true, 'tax_code' => 'ZZTEST85M41F839B']);
        $this->pratica(['codice_fiscale' => 'ZZTEST85M41F839B', 'cognome_cliente' => 'ZZ SOCIETA', 'nome_cliente' => '']);

        // Primo giro con il vecchio comportamento: la "persona" riceve i dati da persona fisica.
        $client->update(['citizenship' => 'Italiana', 'birth_date' => '1985-08-01', 'salary' => 999]);
        $client->branches()->create(['company_id' => Company::first()->id, 'name' => 'Domicilio', 'address' => 'da inserire']);
        $client->branches()->create(['company_id' => Company::first()->id, 'name' => 'Residenza', 'address' => 'da inserire', 'is_main_office' => true]);
        $client->documents()->create(['name' => "Carta d'Identità", 'document_type_id' => DocumentType::first()->id]);

        $this->artisan('clients:create-missing', ['--code' => self::CODES])->assertExitCode(0);

        $client->refresh();
        $this->assertFalse($client->is_person);
        $this->assertTrue($client->is_company);
        $this->assertNull($client->birth_date);
        $this->assertNull($client->citizenship);
        $this->assertNull($client->salary);
        $this->assertSame(['Domicilio'], $client->branches()->pluck('name')->all());
        $this->assertTrue($client->branches()->sole()->is_main_office);
        $this->assertSame(0, $client->documents()->count());
        $this->assertNotNull($client->legalRepresentative);
        $this->assertNotNull($client->pec);

        $this->artisan('clients:create-missing', ['--code' => self::CODES, '--dry-run' => true])
            ->expectsOutputToContain('[dry-run] Persone senza nome da trasformare in societa: 0')
            ->assertExitCode(0);
    }

    public function test_pratiche_without_tax_code_get_a_unique_fake_one_shared_by_the_same_name(): void
    {
        $this->pratica(['codice_pratica' => 'ZZ-A', 'codice_fiscale' => '', 'cognome_cliente' => 'ZZ ROSSI', 'nome_cliente' => 'MARIO']);
        $this->pratica(['codice_pratica' => 'ZZ-B', 'codice_fiscale' => null, 'cognome_cliente' => 'ZZ ROSSI', 'nome_cliente' => 'MARIO']);
        $this->pratica(['codice_pratica' => 'ZZ-C', 'codice_fiscale' => '', 'cognome_cliente' => 'ZZ BIANCHI', 'nome_cliente' => 'LAURA']);
        $this->pratica(['codice_pratica' => 'ZZ-D', 'codice_fiscale' => '', 'cognome_cliente' => 'ZZ SERVICE SRL', 'nome_cliente' => '']);
        $this->pratica(['codice_pratica' => 'ZZ-E', 'codice_fiscale' => '', 'cognome_cliente' => '', 'nome_cliente' => '']);

        // Senza --pratica, con --code il passo non tocca nulla.
        $this->artisan('clients:create-missing', ['--code' => self::CODES])->assertExitCode(0);
        $this->assertSame('', Pratica::where('codice_pratica', 'ZZ-A')->sole()->codice_fiscale);

        $this->artisan('clients:create-missing', ['--code' => self::CODES, '--pratica' => ['ZZ-A', 'ZZ-B', 'ZZ-C', 'ZZ-D', 'ZZ-E']])->assertExitCode(0);

        $this->assertSame('', Pratica::where('codice_pratica', 'ZZ-E')->sole()->codice_fiscale);

        $codes = Pratica::whereIn('codice_pratica', ['ZZ-A', 'ZZ-B', 'ZZ-C', 'ZZ-D'])->pluck('codice_fiscale', 'codice_pratica');

        $this->assertSame($codes['ZZ-A'], $codes['ZZ-B']);
        $this->assertNotSame($codes['ZZ-A'], $codes['ZZ-C']);
        $this->assertMatchesRegularExpression('/^ZZ[A-Z]{4}80A01H501Z$/', $codes['ZZ-A']);
        $this->assertMatchesRegularExpression('/^9\d{10}$/', $codes['ZZ-D']);

        $this->artisan('clients:create-missing', ['--code' => [$codes['ZZ-A'], $codes['ZZ-D']]])->assertExitCode(0);

        $person = Client::where('tax_code', $codes['ZZ-A'])->sole();
        $this->assertTrue($person->is_person);
        $this->assertSame('1980-01-01', $person->birth_date->toDateString());
        $this->assertSame('Roma (RM)', $person->birth_place);

        $company = Client::where('tax_code', $codes['ZZ-D'])->sole();
        $this->assertFalse($company->is_person);
        $this->assertSame($codes['ZZ-D'], $company->vat_number);
    }

    public function test_creates_a_company_from_a_vat_number(): void
    {
        $this->pratica(['codice_fiscale' => '09999999991', 'nome_cliente' => '', 'cognome_cliente' => 'ZZ TEST SRL']);

        $this->artisan('clients:create-missing', ['--code' => self::CODES])->assertExitCode(0);

        $client = Client::where('tax_code', '09999999991')->sole();
        $this->assertSame('ZZ TEST SRL', $client->name);
        $this->assertNull($client->first_name);
        $this->assertFalse($client->is_person);
        $this->assertSame('09999999991', $client->vat_number);
    }

    public function test_does_not_duplicate_existing_clients_by_tax_code_or_vat_number(): void
    {
        Client::create(['name' => 'Esistente', 'is_person' => true, 'first_name' => 'NOME', 'tax_code' => 'ZZTEST85M41F839B']);
        Client::create(['name' => 'Societa', 'is_person' => false, 'vat_number' => '09999999992']);
        $this->pratica(['codice_fiscale' => 'ZZTEST85M41F839B', 'cognome_cliente' => 'BIANCHI', 'nome_cliente' => 'LAURA']);
        $this->pratica(['codice_fiscale' => '09999999992', 'cognome_cliente' => 'SOCIETA SRL']);

        $this->artisan('clients:create-missing', ['--code' => self::CODES])->expectsOutputToContain('Creati: 0 clienti')->assertExitCode(0);

        $this->assertSame(1, Client::where('tax_code', 'ZZTEST85M41F839B')->count());
        $this->assertSame(0, Client::where('tax_code', '09999999992')->count());
    }

    public function test_is_idempotent_and_skips_nameless_pratiche(): void
    {
        $this->pratica(['codice_fiscale' => '', 'cognome_cliente' => 'SENZA CF']);
        $this->pratica(['codice_fiscale' => null, 'cognome_cliente' => 'NULL CF']);
        $this->pratica(['codice_fiscale' => 'ZZTEST70A01H501C', 'cognome_cliente' => '', 'nome_cliente' => '']);
        $this->pratica(['codice_fiscale' => 'ZZTEST90A01H501D', 'cognome_cliente' => 'NERI', 'nome_cliente' => 'MARCO']);

        $this->artisan('clients:create-missing', ['--code' => self::CODES])->expectsOutputToContain('Saltati 1 codici')->assertExitCode(0);
        $this->artisan('clients:create-missing', ['--code' => self::CODES])->expectsOutputToContain('Creati: 0 clienti')->assertExitCode(0);

        $this->assertSame(1, Client::where('tax_code', 'ZZTEST90A01H501D')->count());
        $this->assertSame(0, Client::where('tax_code', 'ZZTEST70A01H501C')->count());
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->pratica(['codice_fiscale' => 'ZZTEST80A01H501A', 'cognome_cliente' => 'ROSSI', 'nome_cliente' => 'MARIO']);

        $this->artisan('clients:create-missing', ['--dry-run' => true, '--code' => self::CODES])->expectsOutputToContain('[dry-run] Da creare: 1')->assertExitCode(0);

        $this->assertSame(0, Client::where('tax_code', 'ZZTEST80A01H501A')->count());
    }
}
