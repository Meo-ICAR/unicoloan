<?php

namespace Tests\Feature;

use App\Models\PdfModule;
use Database\Seeders\SignatureSlotsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SignatureSlotsSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_sets_the_slots_on_both_qav_modules(): void
    {
        $person = PdfModule::factory()->create(['name' => 'QAV Persona fisica']);
        $company = PdfModule::factory()->create(['name' => 'QAV Persona giuridica']);

        $this->seed(SignatureSlotsSeeder::class);

        $this->assertCount(1, $person->refresh()->signature_slots);
        $this->assertEquals(['slot' => 'signer1', 'role' => 'client', 'page' => 3, 'x' => 345, 'y' => 748, 'width' => 142, 'height' => 40], $person->signature_slots[0]);
        $this->assertCount(2, $company->refresh()->signature_slots);
        $this->assertSame('collaborator', $company->signature_slots[1]['role']);
        $this->assertSame(6, $company->signature_slots[1]['page']);
    }

    public function test_it_is_idempotent(): void
    {
        $person = PdfModule::factory()->create(['name' => 'QAV Persona fisica']);

        $this->seed(SignatureSlotsSeeder::class);
        $first = $person->refresh()->signature_slots;
        $this->seed(SignatureSlotsSeeder::class);

        $this->assertSame($first, $person->refresh()->signature_slots);
    }

    public function test_it_does_not_overwrite_existing_slots(): void
    {
        $custom = [['slot' => 'signer1', 'role' => 'client', 'page' => 1, 'x' => 1, 'y' => 2, 'width' => 3, 'height' => 4]];
        $person = PdfModule::factory()->create(['name' => 'QAV Persona fisica', 'signature_slots' => $custom]);

        $this->seed(SignatureSlotsSeeder::class);

        $this->assertEquals($custom, $person->refresh()->signature_slots);
    }

    public function test_it_ignores_missing_modules(): void
    {
        $this->seed(SignatureSlotsSeeder::class);

        $this->assertSame(0, PdfModule::query()->count());
    }
}
