<?php

namespace App\Services\Agenti;

use App\Models\FinancingQuote;
use App\Models\Fornitore;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Memorizza il profilo di una simulazione e assegna il progressivo del produttore.
 */
class FinancingQuoteRecorder
{
    /**
     * @param  array{
     *     contract_type: string,
     *     sex: string,
     *     birth_date: string,
     *     employment_relationship: string,
     *     hired_at: ?string,
     *     starts_at: string,
     *     duration_months: int,
     *     assignable_quota: ?float,
     *     requested_amount: ?float,
     *     age_at_start: int,
     *     seniority_months: ?int
     * }  $profile
     */
    public function store(Fornitore $fornitore, array $profile, ?User $user = null): FinancingQuote
    {
        return DB::connection('mysql')->transaction(function () use ($fornitore, $profile, $user): FinancingQuote {
            $lastNumber = FinancingQuote::query()
                ->where('fornitore_id', $fornitore->getKey())
                ->orderByDesc('number')
                ->lockForUpdate()
                ->value('number');

            $quote = FinancingQuote::query()->create([
                'fornitore_id' => $fornitore->getKey(),
                'number' => ((int) $lastNumber) + 1,
                'user_id' => $user?->getKey(),
                ...$profile,
            ]);

            activity('agenti')
                ->causedBy($user)
                ->performedOn($fornitore)
                ->event('preventivo_memorizzato')
                ->withProperties([
                    'fornitore_id' => $fornitore->getKey(),
                    'financing_quote_id' => $quote->getKey(),
                    'number' => $quote->number,
                ])
                ->log('Preventivo di simulazione memorizzato');

            return $quote;
        });
    }
}
