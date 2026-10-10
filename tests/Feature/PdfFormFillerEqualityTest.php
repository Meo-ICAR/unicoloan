<?php

namespace Tests\Feature;

use App\Enums\ModuleSourceKey;
use App\Models\PdfModule;
use App\Models\PdfModuleField;
use Unico\Core\Pdf\PdfFormFiller;
use Unico\Core\Pdf\ResolvedModuleData;
use Tests\TestCase;

class PdfFormFillerEqualityTest extends TestCase
{
    private function checkbox(string $name, string $when): PdfModuleField
    {
        return new PdfModuleField([
            'pdf_field_name' => $name,
            'pdf_field_type' => 'checkbox',
            'checkbox_on_value' => 'si',
            'checkbox_when' => $when,
        ]);
    }

    public function test_checkbox_when_supports_equality_and_negation(): void
    {
        $module = (new PdfModule)->setRelation('pdfModuleFields', collect([
            $this->checkbox('f1', 'kyc.pep_status=nessuna'),
            $this->checkbox('f2', 'kyc.pep_status=carica_pubblica'),
            $this->checkbox('f3', '!kyc.pep_status=nessuna'),
            $this->checkbox('f4', '!kyc.pep_status=carica_pubblica'),
            $this->checkbox('f5', 'client.is_person'),
            $this->checkbox('f6', '!client.is_person'),
        ]));

        $values = (new PdfFormFiller)->buildFieldValues($module, new ResolvedModuleData([
            'kyc.pep_status' => 'nessuna',
            'client.is_person' => true,
        ]));

        $this->assertSame(
            ['f1' => 'si', 'f2' => 'Off', 'f3' => 'Off', 'f4' => 'si', 'f5' => 'si', 'f6' => 'Off'],
            $values,
        );
    }

    public function test_referenced_keys_ignore_the_compared_value(): void
    {
        foreach (['kyc.pep_status=nessuna', '!kyc.pep_status=nessuna'] as $when) {
            $this->assertSame(
                [ModuleSourceKey::KycPepStatus->value],
                $this->checkbox('f', $when)->referencedKeys(),
            );
        }
    }

    public function test_equals_compares_string_values(): void
    {
        $data = new ResolvedModuleData(['kyc.pep_status' => 'nessuna']);

        $this->assertTrue($data->equals(ModuleSourceKey::KycPepStatus->value, 'nessuna'));
        $this->assertFalse($data->equals(ModuleSourceKey::KycPepStatus->value, 'altro'));
        $this->assertTrue($data->equals(ModuleSourceKey::KycLegalNature->value, ''));
    }
}
