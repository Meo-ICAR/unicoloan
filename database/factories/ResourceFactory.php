<?php

namespace Database\Factories;

use App\Models\Resource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Resource>
 */
class ResourceFactory extends Factory
{
    protected $model = Resource::class;

    public function definition(): array
    {
        $key = fake()->unique()->slug(2);

        return [
            'app_name' => 'admin',
            'key' => $key,
            'name' => str($key)->headline(),
            'group' => fake()->randomElement(['Anagrafiche', 'Compliance', 'System']),
            'min_plan' => 'BASE',
        ];
    }
}
