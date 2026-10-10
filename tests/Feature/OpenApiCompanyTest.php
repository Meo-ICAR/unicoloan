<?php

namespace Tests\Feature;

use App\Enums\ApiProvider;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Clients\Pages\CreateClient;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Clients\RelationManagers\ClientRelationsRelationManager;
use App\Models\ApiCall;
use App\Models\Client;
use App\Models\ClientRelation;
use App\Models\Company;
use App\Models\User;
use App\Services\CompanyShareholderImporter;
use App\Services\OpenApiCompanyService;
use App\Services\PartitaIva;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class OpenApiCompanyTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = ['mysql', 'mysql_proforma'];

    protected function setUp(): void
    {
        parent::setUp();

        // La sede usa company_id di default (schema di branches): in test la societa' va creata.
        Company::factory()->create();

        config([
            'services.openapi.token' => 'tok', 'services.openapi.company_url' => 'https://company.test',
            'services.openapi.cost_advanced' => 0.10, 'services.openapi.cost_shareholders' => 0.03,
        ]);
    }

    private function advancedResponse(array $shareholders = []): array
    {
        return ['success' => true, 'data' => [[
            'companyName' => 'ACME S.R.L.', 'vatCode' => '12485671007', 'taxCode' => '12485671007', 'pec' => 'acme@pec.test',
            'cciaa' => 'RM', 'reaCode' => '123456', 'activityStatus' => 'ATTIVA',
            'atecoClassification' => ['ateco' => ['code' => '62.01']],
            'address' => ['registeredOffice' => ['toponym' => 'VIA', 'streetName' => 'ROMA', 'streetNumber' => '1', 'zipCode' => '00100', 'town' => 'ROMA', 'province' => 'RM']],
            'shareholders' => $shareholders,
        ]]];
    }

    public function test_partita_iva_checksum(): void
    {
        $validator = new PartitaIva;

        $this->assertTrue($validator->isValid('12485671007'));
        $this->assertTrue($validator->isValid('IT12485671007'));
        $this->assertFalse($validator->isValid('12485671008'));
        $this->assertFalse($validator->isValid('1248567100'));
        $this->assertFalse($validator->isValid('ABCDEFGHILM'));
        $this->assertFalse($validator->isValid(null));
    }

    public function test_fetch_company_uses_shareholders_from_the_advanced_response_and_logs_one_call(): void
    {
        Http::fake(['company.test/IT-advanced/*' => Http::response($this->advancedResponse([
            ['name' => 'MARIO', 'surname' => 'ROSSI', 'taxCode' => 'RSSMRA80A01H501U', 'percentShare' => '60'],
        ]))]);

        $result = app(OpenApiCompanyService::class)->fetchCompany('12485671007');

        $this->assertSame('ACME S.R.L.', $result['company']['name']);
        $this->assertSame('62.01', $result['company']['ateco']);
        $this->assertSame('RM 123456', $result['company']['cciaa']);
        $this->assertSame('VIA ROMA 1, 00100 ROMA (RM)', $result['company']['address']);
        $this->assertSame([['is_company' => false, 'name' => 'ROSSI', 'first_name' => 'MARIO', 'tax_code' => 'RSSMRA80A01H501U', 'percent' => 60.0]], $result['shareholders']);
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer tok'));
        $call = ApiCall::query()->sole();
        $this->assertSame(ApiProvider::Openapi, $call->provider);
        $this->assertSame('0.1000', $call->cost);
    }

    public function test_fetch_company_calls_the_shareholders_service_when_missing_and_bills_both(): void
    {
        Http::fake([
            'company.test/IT-advanced/*' => Http::response($this->advancedResponse()),
            'company.test/IT-shareholders/*' => Http::response(['success' => true, 'data' => [
                ['companyName' => 'OPEN HOLDING S.R.L.', 'taxCode' => 16935371001, 'percentShare' => '100'],
            ]]),
        ]);

        $result = app(OpenApiCompanyService::class)->fetchCompany('12485671007');

        $this->assertTrue($result['shareholders'][0]['is_company']);
        $this->assertSame('16935371001', $result['shareholders'][0]['tax_code']);
        $this->assertEqualsWithDelta(0.13, (float) ApiCall::query()->sum('cost'), 0.0001);
    }

    public function test_fetch_company_fails_without_token_or_on_error(): void
    {
        Http::fake(['company.test/*' => Http::response(['success' => false, 'message' => 'Token scaduto'], 401)]);

        try {
            app(OpenApiCompanyService::class)->fetchCompany('12485671007');
            $this->fail('Attesa RuntimeException');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Token scaduto', $e->getMessage());
        }

        $this->assertSame('0.0000', ApiCall::query()->sole()->cost);

        config(['services.openapi.token' => null]);
        $this->expectException(RuntimeException::class);
        app(OpenApiCompanyService::class)->fetchCompany('12485671007');
    }

    public function test_action_fills_company_fields_and_imports_shareholders_linked_to_the_company(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Http::fake(['company.test/IT-advanced/*' => Http::response($this->advancedResponse([
            ['name' => 'MARIO', 'surname' => 'ROSSI', 'taxCode' => 'RSSMRA80A01H501U', 'percentShare' => '60'],
            ['companyName' => 'OPEN HOLDING S.R.L.', 'taxCode' => 16935371001, 'percentShare' => '40'],
        ]))]);
        $company = Client::create(['name' => 'Acme', 'is_person' => false, 'tax_code' => '12485671007']);

        Livewire::test(EditClient::class, ['record' => $company->getKey()])
            ->callAction(TestAction::make('openapi')->schemaComponent('tax_code', 'form'))
            ->assertNotified('Openapi: ACME S.R.L.')
            ->assertSet('data.pec', 'acme@pec.test')
            ->assertSet('data.ateco_code', '62.01');

        $person = Client::query()->where('tax_code', 'RSSMRA80A01H501U')->sole();
        $this->assertSame('ROSSI', $person->name);
        $this->assertSame('MARIO', $person->first_name);
        $this->assertTrue((bool) $person->is_person);
        $this->assertSame('1980-01-01', $person->birth_date->toDateString());
        $holding = Client::query()->where('tax_code', '16935371001')->sole();
        $this->assertFalse((bool) $holding->is_person);
        $this->assertEquals(60, ClientRelation::query()->where('client_id', $person->id)->sole()->shares_percentage);
        $this->assertTrue(ClientRelation::query()->where('client_id', $person->id)->sole()->is_titolare);
        $this->assertSame(2, ClientRelation::query()->where('company_id', (string) $company->id)->count());

        // Rilanciando non si duplicano ne' clienti ne' legami.
        Livewire::test(EditClient::class, ['record' => $company->getKey()])
            ->callAction(TestAction::make('openapi')->schemaComponent('tax_code', 'form'));
        $this->assertSame(2, ClientRelation::query()->where('company_id', (string) $company->id)->count());
        $this->assertSame(1, Client::query()->where('tax_code', 'RSSMRA80A01H501U')->count());
    }

    public function test_real_openapi_response_is_mapped_stored_and_applied_to_the_company(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $payload = json_decode(file_get_contents(base_path('tests/Fixtures/openapi-it-advanced.json')), true);
        Http::fake(['company.test/IT-advanced/*' => Http::response($payload)]);
        $company = Client::create(['name' => 'Openapi', 'is_person' => false, 'tax_code' => '12485671007']);

        Livewire::test(EditClient::class, ['record' => $company->getKey()])
            ->callAction(TestAction::make('openapi')->schemaComponent('tax_code', 'form'))
            ->assertNotified('Openapi: OPENAPI S.P.A.')
            ->assertSet('data.name', 'OPENAPI S.P.A.')
            ->assertSet('data.pec', 'openapi@legalmail.it')
            ->assertSet('data.ateco_code', '62.01')
            ->assertSet('data.cciaa_registration', 'RM 1378273')
            ->assertSet('data.legal_form', "SOCIETA' PER AZIONI")
            ->assertSet('data.sdi_code', 'USAL8PV')
            ->assertSet('data.turnover', 4043407)
            ->assertSet('data.balance_year', 2022);

        $company->refresh();
        $this->assertSame('12485671007', $company->vat_number);
        $this->assertSame("SOCIETA' PER AZIONI", $company->legal_form);
        $this->assertSame('USAL8PV', $company->sdi_code);
        $this->assertSame('ATTIVA', $company->activity_status);
        $this->assertSame('2013-10-20', $company->company_started_at->toDateString());
        $this->assertSame([2022, 14], [$company->balance_year, $company->employees]);
        $this->assertEquals([4043407, 166491, 50000], [$company->turnover, $company->net_worth, $company->share_capital]);
        $this->assertNotNull($company->registry_updated_at);
        $branch = $company->branches()->sole();
        $this->assertSame('VIALE F TOMMASO MARINETTI', $branch->address);
        $this->assertSame('221', $branch->street_number);
        $this->assertSame(['ROMA', '00143', 'RM', 'LAZIO'], [$branch->city, $branch->zip_code, $branch->province, $branch->region]);
        $this->assertTrue((bool) $branch->is_main_office);
        $this->assertSame('2013-10-20', $branch->founded_at->toDateString());

        $holding = Client::query()->where('tax_code', '16935371001')->sole();
        $this->assertEquals(100, ClientRelation::query()->where('company_id', (string) $company->id)->where('client_id', $holding->id)->sole()->shares_percentage);

        Livewire::test(ClientRelationsRelationManager::class, ['ownerRecord' => $company, 'pageClass' => EditClient::class])
            ->assertCanSeeTableRecords(ClientRelation::query()->where('company_id', (string) $company->id)->get())
            ->assertSee('OPEN HOLDING S.R.L.')
            ->assertSee('16935371001')
            ->assertSeeHtml('href="'.ClientResource::getUrl('edit', ['record' => $holding->id]).'"');

        $call = ApiCall::query()->sole();
        $this->assertSame($payload, $call->response);
        $this->assertSame('OPENAPI S.P.A.', $call->summary);
    }

    public function test_openapi_is_not_called_for_an_unsaved_company_or_with_a_modified_vat(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Http::fake();

        Livewire::test(CreateClient::class)
            ->fillForm(['name' => 'Acme', 'is_person' => false, 'tax_code' => '12485671007'])
            ->mountAction(TestAction::make('openapi')->schemaComponent('tax_code', 'form'))
            ->assertNotified('Salva prima il cliente')
            ->assertActionNotMounted();

        $company = Client::create(['name' => 'Acme', 'is_person' => false, 'tax_code' => '12485671007']);

        Livewire::test(EditClient::class, ['record' => $company->getKey()])
            ->set('data.tax_code', '01234567897')
            ->mountAction(TestAction::make('openapi')->schemaComponent('tax_code', 'form'))
            ->assertNotified('Salva le modifiche prima di interrogare');

        Http::assertNothingSent();
        $this->assertSame(0, ApiCall::query()->count());
    }

    public function test_titolare_person_becomes_administrator_only_when_empty(): void
    {
        $importer = app(CompanyShareholderImporter::class);
        $holders = [
            ['is_company' => true, 'name' => 'HOLDING SRL', 'first_name' => null, 'tax_code' => '16935371001', 'percent' => 70.0],
            ['is_company' => false, 'name' => 'ROSSI', 'first_name' => 'MARIO', 'tax_code' => 'RSSMRA80A01H501U', 'percent' => 20.0],
            ['is_company' => false, 'name' => 'BIANCHI', 'first_name' => 'LUCA', 'tax_code' => 'BNCLCU85M01H501Z', 'percent' => 10.0],
        ];
        $company = Client::create(['name' => 'Acme', 'is_person' => false, 'tax_code' => '12485671007']);

        $importer->import($company, $holders);

        $rossi = Client::query()->where('tax_code', 'RSSMRA80A01H501U')->sole();
        $this->assertSame($rossi->id, $company->fresh()->legal_representative_id);

        $other = Client::create(['name' => 'Verdi', 'first_name' => 'Anna', 'is_person' => true]);
        $company->update(['legal_representative_id' => $other->id]);
        $importer->import($company, $holders);

        $this->assertSame($other->id, $company->fresh()->legal_representative_id);
    }
}
