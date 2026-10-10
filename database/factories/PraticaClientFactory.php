<?php

namespace Database\Factories;

use App\Enums\PraticaClientRole;
use App\Models\PraticaClient;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PraticaClient>
 */
class PraticaClientFactory extends Factory
{
    protected $model = PraticaClient::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pratica_id' => (string) Str::uuid(),
            'client_id' => fake()->numberBetween(1, 1000),
            'role' => PraticaClientRole::Applicant,
        ];
    }
}
