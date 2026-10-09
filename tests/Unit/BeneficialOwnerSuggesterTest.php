<?php

namespace Tests\Unit;

use App\Enums\KycControlCriterion;
use App\Models\ClientRelation;
use App\Services\Kyc\BeneficialOwnerSuggester;
use Tests\TestCase;

class BeneficialOwnerSuggesterTest extends TestCase
{
    public function test_only_shares_strictly_above_threshold_qualify_and_are_ordered_by_quota(): void
    {
        $relations = collect([
            $this->relation(1, 25.01),
            $this->relation(2, 25.00),
            $this->relation(3, 60.0),
            $this->relation(4, null),
        ]);

        $owners = (new BeneficialOwnerSuggester)->suggest($relations, 9);

        $this->assertSame([3, 1], array_column($owners, 'client_id'));
        $this->assertSame([1, 2], array_column($owners, 'position'));
        $this->assertSame(KycControlCriterion::Shares, $owners[0]['control_criterion']);
        $this->assertSame(KycControlCriterion::Shares, $owners[1]['control_criterion']);
    }

    public function test_relations_with_past_end_date_are_ignored(): void
    {
        $relations = collect([
            $this->relation(1, 80.0, '2020-01-01'),
            $this->relation(2, 30.0),
        ]);

        $owners = (new BeneficialOwnerSuggester)->suggest($relations, null);

        $this->assertSame([2], array_column($owners, 'client_id'));
    }

    public function test_falls_back_to_legal_representative_when_no_shares_qualify(): void
    {
        $relations = collect([
            $this->relation(1, 25.00),
            $this->relation(2, null),
        ]);

        $owners = (new BeneficialOwnerSuggester)->suggest($relations, 9);

        $this->assertSame([[
            'client_id' => 9,
            'position' => 1,
            'shares_percentage' => null,
            'control_criterion' => KycControlCriterion::Management,
        ]], $owners);
    }

    public function test_returns_empty_when_no_owner_and_no_legal_representative(): void
    {
        $owners = (new BeneficialOwnerSuggester)->suggest(collect([$this->relation(1, 10.0)]), null);

        $this->assertSame([], $owners);
    }

    public function test_caps_suggestions_at_three_owners(): void
    {
        $relations = collect([
            $this->relation(1, 30.0),
            $this->relation(2, 40.0),
            $this->relation(3, 50.0),
            $this->relation(4, 60.0),
            $this->relation(5, 70.0),
        ]);

        $owners = (new BeneficialOwnerSuggester)->suggest($relations, 9);

        $this->assertCount(3, $owners);
        $this->assertSame([5, 4, 3], array_column($owners, 'client_id'));
        $this->assertSame([1, 2, 3], array_column($owners, 'position'));
    }

    private function relation(int $clientId, ?float $shares, ?string $endedAt = null): ClientRelation
    {
        return new ClientRelation([
            'client_id' => $clientId,
            'shares_percentage' => $shares,
            'data_fine_ruolo' => $endedAt,
        ]);
    }
}
