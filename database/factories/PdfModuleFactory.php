<?php

namespace Database\Factories;

use App\Enums\PdfModuleClientScope;
use App\Models\PdfModule;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PdfModule>
 */
class PdfModuleFactory extends Factory
{
    protected $model = PdfModule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => $name,
            'file_path' => 'module/'.Str::slug($name).'-'.fake()->unique()->numerify('####').'.pdf',
            'version' => null,
            'tipi_prodotto' => null,
            'client_scope' => PdfModuleClientScope::Entrambi,
            'is_active' => true,
        ];
    }
}
