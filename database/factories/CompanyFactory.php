<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'vat_number' => fake()->numerify('###########'),
            'vat_name' => fake()->company().' SRL',
            'oam' => 'M'.fake()->numerify('#####'),
            'oam_at' => fake()->dateTimeBetween('-5 years', '-1 year'),
            'oam_name' => 'OAM',
            'numero_iscrizione_rui' => null,
            'ivass' => null,
            'ivass_at' => null,
            'ivass_name' => null,
            'ivass_section' => null,
            'sponsor' => null,
            'company_type' => 'mediatore',
            'page_header' => null,
            'page_footer' => null,
        ];
    }
}
