<?php

namespace Database\Factories;

use App\Enums\FindingSeverity;
use App\Enums\FindingStatus;
use App\Models\Audit;
use App\Models\AuditFinding;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditFinding>
 */
class AuditFindingFactory extends Factory
{
    protected $model = AuditFinding::class;

    public function definition(): array
    {
        return [
            'audit_id' => Audit::factory(),
            'company_id' => Company::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'severity' => fake()->randomElement(FindingSeverity::cases())->value,
            'status' => FindingStatus::Open->value,
            'requires_investigation' => fake()->boolean(30),
            'requires_corrective_action' => true,
            'corrective_action_description' => fake()->sentence(),
            'corrective_action_deadline' => fake()->dateTimeBetween('now', '+3 months'),
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn () => [
            'status' => FindingStatus::Resolved->value,
            'resolved_at' => now(),
            'resolution_notes' => fake()->sentence(),
        ]);
    }
}
