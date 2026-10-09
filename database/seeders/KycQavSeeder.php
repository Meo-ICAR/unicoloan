<?php

namespace Database\Seeders;

use App\Enums\KycActivityLocation;
use App\Enums\KycActivitySector;
use App\Enums\KycCompanyPurpose;
use App\Enums\KycControlCriterion;
use App\Enums\KycEconomicActivity;
use App\Enums\KycExecutorLink;
use App\Enums\KycFinancingNature;
use App\Enums\KycGeographicArea;
use App\Enums\KycIncomeBand;
use App\Enums\KycLegalNature;
use App\Enums\KycPepStatus;
use App\Enums\KycPersonPurpose;
use App\Enums\KycWealthBand;
use App\Enums\ModuleFormatter as Fmt;
use App\Enums\ModuleSourceKey as Key;
use App\Models\PdfModule;
use App\Services\Kyc\KycModuleValues;
use App\Services\Kyc\KycQavGenerator;
use App\Services\PdfFieldSynchronizer;
use Illuminate\Database\Seeder;

/**
 * Collega i due moduli QAV al catalogo documenti (scadenza 12 mesi) e mappa i loro campi ai dati KYC.
 * Idempotente: tocca solo i campi senza `source_key` e senza `checkbox_when`; i tipi gia' monitorati restano invariati.
 * Eseguire dopo PdfModuleSeeder e modules:sync-fields.
 */
class KycQavSeeder extends Seeder
{
    private const PERSON_FILE = 'module/QAV Persona fisica_compressed.pdf';

    private const COMPANY_FILE = 'module/QAV Persona giuridica_compressed.pdf';

    /**
     * Lettera della casella di ogni risposta: "{domanda}{lettera}" (1a, 1b, ... 3m).
     *
     * @var array<int, string>
     */
    private const LETTERS = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'l', 'm'];

    /**
     * @return array<string, array{0: string, 1: class-string<\BackedEnum>}>
     */
    private function personQuestions(): array
    {
        return [
            '1' => ['kyc.pep_status', KycPepStatus::class],
            '2' => ['kyc.economic_activity', KycEconomicActivity::class],
            '3' => ['kyc.activity_sector', KycActivitySector::class],
            '4' => ['kyc.activity_location', KycActivityLocation::class],
            '5' => ['kyc.financing_nature', KycFinancingNature::class],
            '6' => ['kyc.financing_purpose', KycPersonPurpose::class],
            '7' => ['kyc.income_band', KycIncomeBand::class],
            '8' => ['kyc.wealth_band', KycWealthBand::class],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: class-string<\BackedEnum>}>
     */
    private function companyQuestions(): array
    {
        return [
            '1' => ['kyc.legal_nature', KycLegalNature::class],
            '2' => ['kyc.geographic_area', KycGeographicArea::class],
            '3' => ['kyc.financing_purpose', KycCompanyPurpose::class],
            '4' => ['kyc.executor_link', KycExecutorLink::class],
            '5' => ['kyc.executor.pep_status', KycPepStatus::class],
            '6' => ['kyc.owner1.criterion', KycControlCriterion::class],
            '7' => ['kyc.owner1.pep_status', KycPepStatus::class],
            '8' => ['kyc.owner2.criterion', KycControlCriterion::class],
            '9' => ['kyc.owner2.pep_status', KycPepStatus::class],
            '10' => ['kyc.owner3.criterion', KycControlCriterion::class],
            '11' => ['kyc.owner3.pep_status', KycPepStatus::class],
        ];
    }

    /**
     * Campi di testo della persona giuridica: nome campo PDF => prefisso chiave, nell'ordine di PERSON_FIELDS.
     * Il titolare 3 (Text78...Text93) e' per analogia con i titolari 1-2 e non e' verificato sul PDF.
     *
     * @return array<string, array<int, string>>
     */
    private function companyTextBlocks(): array
    {
        return [
            'kyc.executor' => ['dummyFieldName25', 'dummyFieldName26', 'dummyFieldName27', 'dummyFieldName28',
                'Text22', 'Text23', 'Text24', 'Text25', 'Text26', 'Text27', 'Text28', 'Text29', 'Text30', 'Text31', 'Text32', 'Text33'],
            'kyc.owner1' => ['Text39', 'Text40', 'Text41', 'Text42', 'Text43', 'Text44', 'Text45', 'Text46',
                'Text47', 'Text48', 'Text49', 'Text50', 'Text51', 'Text53', 'Text54', 'Text55'],
            'kyc.owner2' => array_map(fn (int $n) => 'Text'.$n, range(59, 74)),
            'kyc.owner3' => array_map(fn (int $n) => 'Text'.$n, range(78, 93)),
        ];
    }

    public function run(): void
    {
        $synchronizer = app(PdfFieldSynchronizer::class);

        $person = PdfModule::where('file_path', self::PERSON_FILE)->first();
        $company = PdfModule::where('file_path', self::COMPANY_FILE)->first();

        if ($person) {
            $this->linkType($synchronizer, $person, KycQavGenerator::SLUG_PERSON, isPerson: true);
            $this->mapCheckboxes($person, $this->personQuestions());
        } else {
            $this->command?->warn('Modulo non registrato, saltato: '.self::PERSON_FILE);
        }

        if ($company) {
            $this->linkType($synchronizer, $company, KycQavGenerator::SLUG_COMPANY, isPerson: false);
            $this->mapCheckboxes($company, $this->companyQuestions());
            $this->mapCompanyText($company);
        } else {
            $this->command?->warn('Modulo non registrato, saltato: '.self::COMPANY_FILE);
        }
    }

    private function linkType(PdfFieldSynchronizer $synchronizer, PdfModule $module, string $slug, bool $isPerson): void
    {
        $type = $synchronizer->linkDocumentType($module);

        if ($type->is_monitored) {
            return;
        }

        $type->update([
            'slug' => $slug,
            'is_monitored' => true,
            'duration' => 12,
            'duration_unit' => 'months',
            'is_signed' => true,
            'is_client' => true,
            'is_practice' => false,
            'is_person' => $isPerson,
            'is_company' => ! $isPerson,
            'document_typable' => 'cliente',
        ]);
    }

    /**
     * @param  array<string, array{0: string, 1: class-string<\BackedEnum>}>  $questions
     */
    private function mapCheckboxes(PdfModule $module, array $questions): void
    {
        foreach ($questions as $number => [$key, $enum]) {
            foreach ($enum::cases() as $index => $case) {
                if (! isset(self::LETTERS[$index])) {
                    break;
                }

                $this->mapField($module, $number.self::LETTERS[$index], ['checkbox_when' => $key.'='.$case->value]);
            }
        }
    }

    private function mapCompanyText(PdfModule $module): void
    {
        foreach ($this->companyTextBlocks() as $prefix => $names) {
            foreach (KycModuleValues::PERSON_FIELDS as $index => $field) {
                $isDate = in_array($field, ['birth_date', 'doc_issued_at', 'doc_expires_at'], true);

                $this->mapField($module, $names[$index], [
                    'source_key' => Key::from($prefix.'.'.$field)->value,
                    'formatter' => $isDate ? Fmt::DateIt->value : null,
                ]);
            }
        }

        foreach (['Text97', 'Text100'] as $name) {
            $this->mapField($module, $name, ['source_key' => Key::PraticaOggi->value, 'formatter' => Fmt::DateIt->value]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function mapField(PdfModule $module, string $fieldName, array $attributes): void
    {
        $module->fields()
            ->where('pdf_field_name', $fieldName)
            ->whereNull('source_key')
            ->where(fn ($query) => $query->whereNull('checkbox_when')->orWhere('checkbox_when', ''))
            ->update($attributes);
    }
}
