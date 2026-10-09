<?php

namespace Tests\Feature;

use App\Enums\KycCoverage;
use App\Models\Client;
use App\Models\KycQuestionnaire;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class KycCoverageTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = ['mysql', 'mysql_proforma'];

    private function makeClient(): Client
    {
        return Client::create([
            'name' => 'Rossi',
            'first_name' => 'Mario',
            'is_person' => true,
            'tax_code' => strtoupper(Str::random(16)),
        ]);
    }

    private function approvedWithExpiry(Client $client, ?Carbon $expires, string $verifiedAt = 'now'): KycQuestionnaire
    {
        $questionnaire = KycQuestionnaire::factory()->approved()->create([
            'client_id' => $client->id,
            'verified_at' => Carbon::parse($verifiedAt),
        ]);

        if ($expires !== null) {
            $doc = $client->documents()->create(['name' => 'QAV', 'status' => 'caricato', 'spatie_collection' => 'documents']);
            $doc->forceFill(['expires_at' => $expires])->saveQuietly();
            $questionnaire->update(['document_id' => $doc->id]);
        }

        return $questionnaire;
    }

    public function test_client_without_questionnaires_is_missing(): void
    {
        $this->assertSame(KycCoverage::Missing, $this->makeClient()->kycCoverage());
    }

    public function test_client_with_only_a_draft_is_missing(): void
    {
        $client = $this->makeClient();
        KycQuestionnaire::factory()->draft()->create(['client_id' => $client->id]);

        $this->assertSame(KycCoverage::Missing, $client->kycCoverage());
    }

    public function test_approved_without_document_is_complete(): void
    {
        $client = $this->makeClient();
        $this->approvedWithExpiry($client, null);

        $this->assertSame(KycCoverage::Complete, $client->kycCoverage());
    }

    public function test_approved_with_past_document_expiry_is_expired(): void
    {
        $client = $this->makeClient();
        $this->approvedWithExpiry($client, today()->subDay());

        $this->assertSame(KycCoverage::Expired, $client->kycCoverage());
    }

    public function test_approved_with_future_document_expiry_is_complete(): void
    {
        $client = $this->makeClient();
        $this->approvedWithExpiry($client, today()->addYear());

        $this->assertSame(KycCoverage::Complete, $client->kycCoverage());
    }

    public function test_latest_approved_wins_over_older_expired_and_newer_draft(): void
    {
        $client = $this->makeClient();
        $this->approvedWithExpiry($client, today()->subDay(), '-2 years');
        $latest = $this->approvedWithExpiry($client, today()->addYear(), '-1 month');
        KycQuestionnaire::factory()->draft()->create(['client_id' => $client->id, 'verified_at' => now()]);

        $this->assertTrue($client->currentKyc()->is($latest));
        $this->assertSame(KycCoverage::Complete, $client->kycCoverage());
    }

    public function test_coverage_by_client_maps_only_clients_with_an_approved_questionnaire(): void
    {
        $expired = $this->makeClient();
        $complete = $this->makeClient();
        $draftOnly = $this->makeClient();
        $this->approvedWithExpiry($expired, today()->subDay());
        $this->approvedWithExpiry($complete, today()->addYear());
        KycQuestionnaire::factory()->draft()->create(['client_id' => $draftOnly->id]);

        $map = KycQuestionnaire::coverageByClient();

        $this->assertSame(KycCoverage::Expired, $map[$expired->id]);
        $this->assertSame(KycCoverage::Complete, $map[$complete->id]);
        $this->assertFalse($map->has($draftOnly->id));
    }
}
