<?php

namespace Database\Factories;

use App\Enums\SignatureRequestStatus;
use App\Models\SignatureRequest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SignatureRequest>
 */
class SignatureRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_id' => (string) Str::uuid(),
            'provider' => 'fake',
            'status' => SignatureRequestStatus::Pending,
        ];
    }
}
