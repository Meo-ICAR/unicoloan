<?php

namespace Tests\Feature;

use App\Enums\ModuleSourceKey as Key;
use App\Models\DocumentType;
use App\Models\PdfModule;
use App\Models\PdfModuleField;
use Database\Seeders\KycQavSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class KycQavSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = ['mysql', 'mysql_proforma'];

    private function module(string $file, string $name, array $fields): PdfModule
    {
        $module = PdfModule::factory()->create(['name' => $name, 'file_path' => 'module/'.$file]);

        foreach ($fields as $field) {
            PdfModuleField::factory()->create(['pdf_module_id' => $module->id, 'pdf_field_name' => $field]);
        }

        return $module;
    }

    private function modules(): array
    {
        return [
            $this->module('QAV Persona fisica_compressed.pdf', 'QAV Persona fisica', ['1a', '1e', '3m', 'Text1']),
            $this->module('QAV Persona giuridica_compressed.pdf', 'QAV Persona giuridica', ['Text39', 'Text40', 'Text43', 'Text97', '6a']),
        ];
    }

    private function field(PdfModule $module, string $name): PdfModuleField
    {
        return $module->pdfModuleFields()->where('pdf_field_name', $name)->firstOrFail();
    }

    public function test_it_maps_checkboxes_and_text_fields(): void
    {
        [$person, $company] = $this->modules();

        $this->seed(KycQavSeeder::class);

        $this->assertSame('kyc.pep_status=carica_pubblica', $this->field($person, '1a')->checkbox_when);
        $this->assertSame('kyc.pep_status=nessuna', $this->field($person, '1e')->checkbox_when);
        $this->assertSame('kyc.activity_sector=nessuna_condizione', $this->field($person, '3m')->checkbox_when);
        $this->assertNull($this->field($person, 'Text1')->source_key);
        $this->assertSame(Key::KycOwner1Name->value, $this->field($company, 'Text39')->source_key);
        $this->assertSame(Key::KycOwner1FirstName->value, $this->field($company, 'Text40')->source_key);
        $this->assertSame('date_it', $this->field($company, 'Text43')->formatter->value);
        $this->assertSame(Key::PraticaOggi->value, $this->field($company, 'Text97')->source_key);
        $this->assertNotNull($this->field($company, '6a')->checkbox_when);
    }

    public function test_it_is_idempotent_and_does_not_overwrite_existing_mappings(): void
    {
        [$person] = $this->modules();
        $this->field($person, '1a')->update(['checkbox_when' => 'custom=1']);
        $this->field($person, '1e')->update(['source_key' => Key::ClienteNome->value]);

        $this->seed(KycQavSeeder::class);
        $this->seed(KycQavSeeder::class);

        $this->assertSame('custom=1', $this->field($person, '1a')->checkbox_when);
        $this->assertNull($this->field($person, '1e')->checkbox_when);
        $this->assertSame('kyc.activity_sector=nessuna_condizione', $this->field($person, '3m')->checkbox_when);
        $this->assertSame(1, DocumentType::query()->where('slug', 'qav-persona-fisica')->count());
    }

    public function test_it_sets_expiry_only_when_type_is_not_monitored(): void
    {
        [$person, $company] = $this->modules();
        $existing = DocumentType::query()->create([
            'name' => 'Altro', 'slug' => 'altro', 'nature' => 'template_fillable',
            'is_monitored' => true, 'duration' => 3, 'duration_unit' => 'days',
        ]);
        $company->update(['document_type_id' => $existing->id]);

        $this->seed(KycQavSeeder::class);

        $type = $person->fresh()->documentType;
        $this->assertTrue($type->is_monitored);
        $this->assertSame(12, $type->duration);
        $this->assertSame('months', $type->duration_unit);
        $this->assertSame('qav-persona-fisica', $type->slug);
        $this->assertSame(3, $existing->fresh()->duration);
        $this->assertSame('altro', $existing->fresh()->slug);
    }
}
