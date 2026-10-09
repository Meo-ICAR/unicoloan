<?php

namespace Database\Seeders;

use App\Models\PdfModule;
use Illuminate\Database\Seeder;

/**
 * Imposta i riquadri di firma dei due QAV (coordinate poppler: origine in alto a sinistra, punti, A4 595 x 842).
 * Idempotente: non tocca i moduli che hanno gia' degli slot e salta quelli assenti.
 */
class SignatureSlotsSeeder extends Seeder
{
    /**
     * @var array<string, array<int, array{slot: string, role: string, page: int, x: int, y: int, width: int, height: int}>>
     */
    private const SLOTS = [
        'QAV Persona fisica' => [
            ['slot' => 'cliente', 'role' => 'client', 'page' => 3, 'x' => 345, 'y' => 748, 'width' => 142, 'height' => 40],
        ],
        'QAV Persona giuridica' => [
            ['slot' => 'cliente', 'role' => 'client', 'page' => 6, 'x' => 330, 'y' => 345, 'width' => 142, 'height' => 40],
            ['slot' => 'collaboratore', 'role' => 'collaborator', 'page' => 6, 'x' => 40, 'y' => 655, 'width' => 142, 'height' => 40],
        ],
    ];

    public function run(): void
    {
        foreach (self::SLOTS as $name => $slots) {
            $module = PdfModule::query()->where('name', $name)->first();

            if ($module === null || ! empty($module->signature_slots)) {
                continue;
            }

            $module->update(['signature_slots' => $slots]);
        }
    }
}
