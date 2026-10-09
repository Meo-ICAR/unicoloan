<?php

namespace App\Services\Kyc;

use App\Enums\KycControlCriterion;
use App\Models\ClientRelation;
use Illuminate\Support\Collection;

class BeneficialOwnerSuggester
{
    public const THRESHOLD = 25.0;

    public const MAX_OWNERS = 3;

    /**
     * @param  Collection<int, ClientRelation>  $relations
     * @return array<int, array{client_id: int, position: int, shares_percentage: float|null, control_criterion: KycControlCriterion}>
     */
    public function suggest(Collection $relations, ?int $legalRepresentativeId): array
    {
        $owners = $relations
            ->filter(fn (ClientRelation $r) => $r->data_fine_ruolo === null || $r->data_fine_ruolo->isFuture())
            ->filter(fn (ClientRelation $r) => (float) $r->shares_percentage > self::THRESHOLD)
            ->sortByDesc(fn (ClientRelation $r) => (float) $r->shares_percentage)
            ->take(self::MAX_OWNERS)
            ->values()
            ->map(fn (ClientRelation $r, int $index) => [
                'client_id' => (int) $r->client_id,
                'position' => $index + 1,
                'shares_percentage' => (float) $r->shares_percentage,
                'control_criterion' => KycControlCriterion::Shares,
            ])
            ->all();

        if ($owners === [] && $legalRepresentativeId !== null) {
            return [[
                'client_id' => $legalRepresentativeId,
                'position' => 1,
                'shares_percentage' => null,
                'control_criterion' => KycControlCriterion::Management,
            ]];
        }

        return $owners;
    }
}
