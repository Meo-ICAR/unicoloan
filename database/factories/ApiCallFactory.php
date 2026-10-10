<?php

namespace Database\Factories;

use App\Enums\ApiCallStatus;
use App\Enums\ApiProvider;
use App\Models\ApiCall;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApiCall>
 */
class ApiCallFactory extends Factory
{
    protected $model = ApiCall::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => ApiProvider::Cerved,
            'operation' => 'score',
            'status' => ApiCallStatus::Success,
            'http_status' => 200,
            'duration_ms' => 250,
            'cost' => 0.5,
            'currency' => 'EUR',
        ];
    }
}
