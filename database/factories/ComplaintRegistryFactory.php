<?php

namespace Database\Factories;

use App\Enums\ComplaintCategory;
use App\Enums\ComplaintMacroCategory;
use App\Enums\ComplaintStatus;
use App\Enums\ReceptionChannel;
use App\Models\Company;
use App\Models\ComplaintRegistry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComplaintRegistry>
 */
class ComplaintRegistryFactory extends Factory
{
    protected $model = ComplaintRegistry::class;

    public function definition(): array
    {
        $receivedAt = fake()->dateTimeBetween('-4 months', 'now');

        return [
            'company_id' => Company::factory(),
            'received_at' => $receivedAt,
            'reception_channel' => fake()->randomElement(ReceptionChannel::cases())->value,
            'receiving_email' => fake()->companyEmail(),
            'complainant_name' => fake()->name(),
            'complainant_email' => fake()->safeEmail(),
            'macro_category' => fake()->randomElement(ComplaintMacroCategory::cases())->value,
            'category' => fake()->randomElement(ComplaintCategory::cases())->value,
            'description' => fake()->paragraph(),
            'financial_impact' => fake()->randomFloat(2, 0, 5000),
            'status' => ComplaintStatus::Open->value,
            'deadline_at' => (clone $receivedAt)->modify('+45 days'),
            'is_extended' => false,
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn () => [
            'status' => ComplaintStatus::Accepted->value,
            'resolved_at' => now(),
            'resolution_notes' => fake()->sentence(),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'status' => ComplaintStatus::Open->value,
            'deadline_at' => now()->subDays(5),
        ]);
    }
}
