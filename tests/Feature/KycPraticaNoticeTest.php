<?php

namespace Tests\Feature;

use App\Filament\Resources\Praticas\Pages\EditPratica;
use App\Models\Client;
use App\Models\KycQuestionnaire;
use App\Models\PROFORMA\Pratica;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class KycPraticaNoticeTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = ['mysql', 'mysql_proforma'];

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create());
    }

    private function makeClient(): Client
    {
        return Client::create([
            'name' => 'Rossi',
            'first_name' => 'Mario',
            'is_person' => true,
            'is_company' => false,
            'tax_code' => strtoupper(Str::random(16)),
        ]);
    }

    private function approve(Client $client, Carbon $expires): void
    {
        $questionnaire = KycQuestionnaire::factory()->approved()->create(['client_id' => $client->id]);
        $doc = $client->documents()->create(['name' => 'QAV', 'status' => 'caricato', 'spatie_collection' => 'documents']);
        $doc->forceFill(['expires_at' => $expires])->saveQuietly();
        $questionnaire->update(['document_id' => $doc->id]);
    }

    private function makePratica(?string $codiceFiscale): Pratica
    {
        return Pratica::create([
            'id' => (string) Str::uuid(),
            'codice_pratica' => 'P-KYC-1',
            'codice_fiscale' => $codiceFiscale,
        ]);
    }

    public function test_warns_when_client_has_no_kyc(): void
    {
        $pratica = $this->makePratica($this->makeClient()->tax_code);

        Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->assertSee('KYC mancante')
            ->assertDontSee('KYC scaduto');
    }

    public function test_warns_when_kyc_is_expired(): void
    {
        $client = $this->makeClient();
        $this->approve($client, today()->subDay());
        $pratica = $this->makePratica($client->tax_code);

        Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->assertSee('KYC scaduto')
            ->assertDontSee('KYC mancante');
    }

    public function test_no_warning_when_kyc_is_valid(): void
    {
        $client = $this->makeClient();
        $this->approve($client, today()->addYear());
        $pratica = $this->makePratica($client->tax_code);

        Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->assertDontSee('KYC mancante')
            ->assertDontSee('KYC scaduto');
    }

    public function test_no_warning_when_pratica_has_no_matching_client(): void
    {
        $pratica = $this->makePratica('NESSUNCLIENTE0000');

        Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->assertSuccessful()
            ->assertDontSee('KYC mancante')
            ->assertDontSee('KYC scaduto');

        $senzaCodice = $this->makePratica(null);

        Livewire::test(EditPratica::class, ['record' => $senzaCodice->getKey()])
            ->assertSuccessful()
            ->assertDontSee('KYC mancante');
    }
}
