<?php

namespace Tests\Feature;

use App\Filament\Resources\Clients\Pages\EditClient;
use App\Models\Client;
use App\Models\PROFORMA\Pratica;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ClientEmployerIbanTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = ['mysql', 'mysql_proforma'];

    private function makeClient(array $attributes = []): Client
    {
        return Client::create(array_merge([
            'name' => 'Rossi',
            'first_name' => 'Mario',
            'is_person' => true,
            'tax_code' => strtoupper(Str::random(16)),
        ], $attributes));
    }

    public function test_client_stores_iban_and_links_an_employer(): void
    {
        $employer = $this->makeClient(['name' => 'Acme Spa', 'first_name' => null, 'is_person' => false]);
        $employee = $this->makeClient([
            'iban' => 'IT60X0542811101000000123456',
            'employer_id' => $employer->id,
        ]);

        $employee->refresh();

        $this->assertSame('IT60X0542811101000000123456', $employee->iban);
        $this->assertTrue($employee->employer->is($employer));
        $this->assertTrue($employer->employees->contains($employee));
    }

    public function test_employer_is_optional_and_nulled_when_the_employer_is_deleted(): void
    {
        $standalone = $this->makeClient();
        $this->assertNull($standalone->employer);

        $employer = $this->makeClient(['name' => 'Acme Spa', 'is_person' => false]);
        $employee = $this->makeClient(['employer_id' => $employer->id]);

        $employer->delete();

        $this->assertNull($employee->fresh()->employer_id);
    }

    public function test_client_pratiches_resolves_through_the_tax_code(): void
    {
        $client = $this->makeClient();
        $pratica = Pratica::create([
            'id' => (string) Str::uuid(),
            'codice_pratica' => 'P-TEST-1',
            'codice_fiscale' => $client->tax_code,
        ]);

        $this->assertTrue($client->clientPratiches->contains('id', $pratica->id));
    }

    public function test_client_form_saves_a_normalized_iban_and_the_employer(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create());

        $employer = $this->makeClient(['name' => 'Acme Spa', 'first_name' => null, 'is_person' => false]);
        $client = $this->makeClient();

        Livewire::test(EditClient::class, ['record' => $client->getKey()])
            ->fillForm([
                'iban' => 'it60 x054 2811 1010 0000 0123 456',
                'employer_id' => $employer->id,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $client->refresh();

        $this->assertSame('IT60X0542811101000000123456', $client->iban);
        $this->assertSame($employer->id, $client->employer_id);
    }
}
