<?php

namespace Database\Factories;

use App\Models\PdfModule;
use App\Models\PdfModuleField;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PdfModuleField>
 */
class PdfModuleFieldFactory extends Factory
{
    protected $model = PdfModuleField::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pdf_module_id' => PdfModule::factory(),
            'pdf_field_name' => 'Text'.fake()->unique()->numerify('####'),
            'pdf_field_type' => 'text',
            'source_key' => null,
            'formatter' => null,
            'checkbox_on_value' => null,
            'checkbox_when' => null,
        ];
    }

    public function checkbox(string $onValue = 'si'): static
    {
        return $this->state(fn () => [
            'pdf_field_type' => 'checkbox',
            'checkbox_on_value' => $onValue,
        ]);
    }
}
