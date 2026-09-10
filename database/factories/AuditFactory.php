<?php

namespace Database\Factories;

use App\Enums\AuditStatus;
use App\Models\Audit;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Audit>
 */
class AuditFactory extends Factory
{
    protected $model = Audit::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => 'Audit '.fake()->unique()->numberBetween(1, 99999),
            'auditable_type' => 'company',
            'auditable_id' => Company::factory(),
            'auditor_name' => fake()->name(),
            'scheduled_at' => fake()->dateTimeBetween('-6 months', '-1 month'),
            'executed_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'status' => AuditStatus::COMPLETED->value,
            'origin_type' => 'internal',
            'execution_method' => fake()->randomElement(['documentale', 'audit', 'ispezione']),
            'scope' => fake()->sentence(),
            'outcome' => fake()->randomElement(['conforme', 'non_conforme']),
            'severity' => fake()->randomElement(['Alto', 'Medio', 'Basso']),
            'summary' => fake()->paragraph(),
        ];
    }

    public function conforme(): static
    {
        return $this->state(fn () => ['outcome' => 'conforme']);
    }

    public function conRilievi(): static
    {
        return $this->state(fn () => ['outcome' => 'non_conforme', 'severity' => 'Alto']);
    }
}
