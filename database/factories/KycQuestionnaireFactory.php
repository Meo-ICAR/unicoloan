<?php

namespace Database\Factories;

use App\Enums\KycPepStatus;
use App\Enums\KycRiskLevel;
use App\Enums\KycStatus;
use App\Models\KycQuestionnaire;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KycQuestionnaire>
 */
class KycQuestionnaireFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => 1,
            'status' => KycStatus::Draft,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['status' => KycStatus::Draft]);
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => KycStatus::Approved,
            'risk_level' => KycRiskLevel::Medium,
            'verified_at' => now(),
            'verified_by' => 'Tester',
            'pep_status' => KycPepStatus::None,
        ]);
    }
}
