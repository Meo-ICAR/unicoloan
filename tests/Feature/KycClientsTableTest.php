<?php

namespace Tests\Feature;

use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Clients\Pages\ListClients;
use App\Models\Client;
use App\Models\KycQuestionnaire;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class KycClientsTableTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = ['mysql', 'mysql_proforma'];

    private Client $missing;

    private Client $expired;

    private Client $complete;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create());

        $this->missing = $this->makeClient('ZkycMissing');
        $this->expired = $this->makeClient('ZkycExpired');
        $this->complete = $this->makeClient('ZkycComplete');

        $this->approve($this->expired, today()->subDay());
        $this->approve($this->complete, today()->addYear());
    }

    private function makeClient(string $name): Client
    {
        return Client::create([
            'name' => $name,
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

    public function test_filter_missing_shows_only_clients_without_approved_kyc(): void
    {
        Livewire::test(ListClients::class)
            ->searchTable('Zkyc')
            ->filterTable('kyc', 'missing')
            ->assertCanSeeTableRecords([$this->missing])
            ->assertCanNotSeeTableRecords([$this->expired, $this->complete]);
    }

    public function test_filter_expired_shows_only_expired_clients(): void
    {
        Livewire::test(ListClients::class)
            ->searchTable('Zkyc')
            ->filterTable('kyc', 'expired')
            ->assertCanSeeTableRecords([$this->expired])
            ->assertCanNotSeeTableRecords([$this->missing, $this->complete]);
    }

    public function test_filter_complete_shows_only_complete_clients(): void
    {
        Livewire::test(ListClients::class)
            ->searchTable('Zkyc')
            ->filterTable('kyc', 'complete')
            ->assertCanSeeTableRecords([$this->complete])
            ->assertCanNotSeeTableRecords([$this->missing, $this->expired]);
    }

    public function test_kyc_and_high_risk_filters_combine_without_leaking_or_conditions(): void
    {
        $riskyMissing = $this->makeClient('ZkycRiskyMissing');
        $riskyMissing->update(['is_pep' => true]);
        $riskyComplete = $this->makeClient('ZkycRiskyComplete');
        $riskyComplete->update(['is_sanctioned' => true]);
        $this->approve($riskyComplete, today()->addYear());

        Livewire::test(ListClients::class)
            ->searchTable('Zkyc')
            ->filterTable('kyc', 'missing')
            ->filterTable('high_risk', true)
            ->assertCanSeeTableRecords([$riskyMissing])
            ->assertCanNotSeeTableRecords([$this->missing, $this->expired, $this->complete, $riskyComplete]);
    }

    public function test_kyc_column_renders_each_coverage_label(): void
    {
        Livewire::test(ListClients::class)
            ->searchTable('Zkyc')
            ->assertCanSeeTableRecords([$this->missing, $this->expired, $this->complete])
            ->assertSee('Mancante')
            ->assertSee('Scaduto')
            ->assertSee('Completo');
    }

    public function test_edit_page_renders_with_and_without_kyc(): void
    {
        Livewire::test(EditClient::class, ['record' => $this->missing->getKey()])
            ->assertSuccessful()
            ->assertSee('KYC (adeguata verifica)');

        Livewire::test(EditClient::class, ['record' => $this->complete->getKey()])
            ->assertSuccessful()
            ->assertSee('KYC (adeguata verifica)')
            ->assertDontSee('Ultimo questionario di adeguata verifica approvato');
    }
}
