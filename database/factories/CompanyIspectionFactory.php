<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CompanyIspection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyIspection>
 */
class CompanyIspectionFactory extends Factory
{
    protected $model = CompanyIspection::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $dal = fake()->dateTimeBetween('-2 years', '-3 months');
        $al = fake()->dateTimeBetween($dal, 'now');

        return [
            'company_id' => Company::factory(),
            'name' => fake()->randomElement([
                'Ispezione OAM ordinaria',
                'Verifica documentale Banca d\'Italia',
                'Audit di conformità AML',
                'Controllo requisiti organizzativi',
                'Ispezione periodica OAM',
            ]),
            'dal' => $dal,
            'al' => $al,
            'execution_method' => fake()->randomElement(['documentale', 'onsite', '']),
            'ispectorName' => fake()->name(),
            'n' => fake()->numberBetween(1, 99),
        ];
    }
}
