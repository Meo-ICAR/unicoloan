<?php

namespace Database\Factories;

use App\Models\FinancingQuote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancingQuote>
 */
class FinancingQuoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fornitore_id' => 1,
            'number' => 1,
            'contract_type' => 'cessione',
            'sex' => 'M',
            'birth_date' => '1980-02-20',
            'employment_relationship' => 'statale',
            'hired_at' => '2015-06-01',
            'starts_at' => '2027-01-13',
            'duration_months' => 120,
            'assignable_quota' => 280,
            'requested_amount' => null,
            'age_at_start' => 46,
            'seniority_months' => 139,
            'user_id' => null,
        ];
    }
}
