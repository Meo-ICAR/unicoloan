<?php

namespace App\Services;

use App\Models\DocumentType;
use App\Models\PdfModule;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Allinea `pdf_modules` / `pdf_module_fields` ai PDF presenti in storage/app/public/module.
 * Non modifica mai le mappature gia' fatte e non cancella mai righe.
 */
class PdfFieldSynchronizer
{
    public const MODULE_DIRECTORY = 'module';

    public function __construct(private PdfFormFiller $filler) {}

    /**
     * @return array<int, string> percorsi relativi dei PDF, ordinati
     */
    public function discoverFiles(): array
    {
        $files = array_filter(
            Storage::disk('public')->files(self::MODULE_DIRECTORY),
            fn (string $path) => strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'pdf',
        );

        sort($files);

        return array_values($files);
    }

    /**
     * @return array{module: PdfModule, created: array<int, string>, removed: array<int, string>}
     */
    public function sync(string $relativePath): array
    {
        $disk = Storage::disk('public');

        if (! $disk->exists($relativePath)) {
            throw PdfFormException::missingTemplate($relativePath);
        }

        if (! str_starts_with($disk->get($relativePath), '%PDF')) {
            throw PdfFormException::pdftkFailed('file non PDF: '.$relativePath);
        }

        $pdf = $this->filler->open($disk->path($relativePath));
        $dataFields = $pdf->getDataFields();

        if ($dataFields === false) {
            throw PdfFormException::pdftkFailed($pdf->getError());
        }

        $module = PdfModule::firstOrCreate(
            ['file_path' => $relativePath],
            ['name' => $this->moduleNameFromPath($relativePath)],
        );

        $found = [];
        $created = [];

        foreach ($dataFields->__toArray() as $field) {
            $name = $field['FieldName'] ?? null;
            $type = $field['FieldType'] ?? null;

            if ($name === null || ! in_array($type, ['Text', 'Button'], true)) {
                continue;
            }

            $states = Arr::wrap($field['FieldStateOption'] ?? []);

            // Un Button senza stati e' un pulsante (stampa, svuota campi), non una casella.
            if ($type === 'Button' && $states === []) {
                continue;
            }

            $isCheckbox = $type === 'Button';
            $onValue = $isCheckbox
                ? Arr::first($states, fn (string $state) => strcasecmp($state, 'Off') !== 0)
                : null;

            $row = $module->fields()->firstOrCreate(
                ['pdf_field_name' => $name],
                ['pdf_field_type' => $isCheckbox ? 'checkbox' : 'text', 'checkbox_on_value' => $onValue],
            );

            if ($row->wasRecentlyCreated) {
                $created[] = $name;
            }

            $found[] = $name;
        }

        $removed = $module->fields()
            ->whereNotIn('pdf_field_name', $found)
            ->pluck('pdf_field_name')
            ->all();

        return ['module' => $module, 'created' => $created, 'removed' => $removed];
    }

    /**
     * Collega il modulo al catalogo `document_types`: riusa il tipo con lo stesso slug, altrimenti
     * ne crea uno "Modulo da Compilare". Un modulo gia' collegato non viene toccato.
     */
    public function linkDocumentType(PdfModule $module): DocumentType
    {
        if ($module->documentType) {
            return $module->documentType;
        }

        $type = DocumentType::query()->firstOrCreate(
            ['slug' => Str::slug($module->name)],
            [
                'name' => $module->name,
                'nature' => 'template_fillable',
                'doctype' => 'modulo',
                'is_template' => true,
                'is_person' => true,
                'is_company' => true,
                'is_practice' => true,
            ],
        );

        $module->update(['document_type_id' => $type->getKey()]);

        return $type;
    }

    public function moduleNameFromPath(string $path): string
    {
        $name = str_replace('_compressed', '', pathinfo($path, PATHINFO_FILENAME));

        return trim((string) preg_replace('/\s+/', ' ', $name));
    }
}
