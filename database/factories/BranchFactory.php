<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->company().' — Sede '.fake()->city(),
            'address' => fake()->streetName(),
            'street_number' => fake()->buildingNumber(),
            'city' => fake()->city(),
            'zip_code' => fake()->postcode(),
            'province' => fake()->city(),
            'region' => fake()->randomElement([
                'Abruzzo', 'Basilicata', 'Calabria', 'Campania', 'Emilia-Romagna',
                'Friuli-Venezia Giulia', 'Lazio', 'Liguria', 'Lombardia', 'Marche',
                'Molise', 'Piemonte', 'Puglia', 'Sardegna', 'Sicilia', 'Toscana',
                'Trentino-Alto Adige', 'Umbria', "Valle d'Aosta", 'Veneto',
            ]),
            'branchable_type' => null,
            'branchable_id' => null,
            'is_main_office' => false,
            'manager_first_name' => fake()->firstName(),
            'manager_last_name' => fake()->lastName(),
            'manager_tax_code' => strtoupper(fake()->bothify('??????##?##?###?')),
            'founded_at' => fake()->dateTimeBetween('-5 years', 'now'),
            'dismissed_at' => null,
        ];
    }

    /**
     * Indicate this is the main office.
     */
    public function mainOffice(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_main_office' => true,
        ]);
    }

    /**
     * Indicate this branch has been dismissed.
     */
    public function dismissed(): static
    {
        return $this->state(fn (array $attributes) => [
            'dismissed_at' => fake()->dateTimeBetween('-1 year', 'now'),
        ]);
    }
}
