<?php

namespace App\Services;

use App\Enums\ModuleFormatter;
use App\Enums\ModuleSourceKey;
use App\Models\PdfModule;
use App\Models\PdfModuleField;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use mikehaertl\pdftk\Pdf;

/**
 * Compila i campi AcroForm di un modulo PDF con pdftk (fill_form).
 */
class PdfFormFiller
{
    /**
     * Apre un PDF con il binario pdftk configurato (config services.pdftk.binary).
     */
    public function open(string $absolutePath): Pdf
    {
        return new Pdf($absolutePath, ['command' => config('services.pdftk.binary', 'pdftk')]);
    }

    /**
     * @return array<string, string> nome campo PDF => valore da scrivere
     */
    public function buildFieldValues(PdfModule $module, ResolvedModuleData $data): array
    {
        $values = [];

        foreach ($module->fields as $field) {
            if ($field->isCheckbox()) {
                $checked = $this->isCheckboxChecked($field, $data);

                if ($checked !== null) {
                    $values[$field->pdf_field_name] = $checked ? ($field->checkbox_on_value ?: 'Yes') : 'Off';
                }

                continue;
            }

            if ($field->source_key === null) {
                continue;
            }

            $text = ModuleFormatter::format($data->get($field->source_key), $field->formatter);

            if ($text !== '') {
                $values[$field->pdf_field_name] = $text;
            }
        }

        return $values;
    }

    /**
     * @return string contenuto binario del PDF compilato
     */
    public function fill(PdfModule $module, ResolvedModuleData $data, bool $flatten = false): string
    {
        return $this->render($module, $this->buildFieldValues($module, $data), $flatten);
    }

    /**
     * PDF diagnostico: ogni campo testo mostra il proprio nome, le checkbox sono spuntate,
     * cosi' chi mappa vede dove sta ciascun campo sul modulo.
     */
    public function fillDiagnostic(PdfModule $module): string
    {
        $values = [];

        foreach ($module->fields as $field) {
            $values[$field->pdf_field_name] = $field->isCheckbox()
                ? ($field->checkbox_on_value ?: 'Yes')
                : $field->pdf_field_name;
        }

        return $this->render($module, $values, false);
    }

    /**
     * Scrive il PDF diagnostico sul disco public e ritorna il percorso relativo.
     * Contiene solo nomi di campo, nessun dato personale.
     */
    public function storeDiagnostic(PdfModule $module): string
    {
        $path = 'module-diagnostica/'.Str::slug($module->name).'.pdf';

        Storage::disk('public')->put($path, $this->fillDiagnostic($module));

        return $path;
    }

    /**
     * Esito della casella: true/false se configurata, null se non c'e' nulla da fare.
     * `checkbox_when` (con `!` iniziale per negare) ha la precedenza su `source_key`.
     */
    private function isCheckboxChecked(PdfModuleField $field, ResolvedModuleData $data): ?bool
    {
        if (filled($field->checkbox_when)) {
            $negate = str_starts_with($field->checkbox_when, '!');
            $key = ModuleSourceKey::tryFrom(ltrim($field->checkbox_when, '!'));

            return $key === null ? null : ($data->isTruthy($key) !== $negate);
        }

        return $field->source_key instanceof ModuleSourceKey ? $data->isTruthy($field->source_key) : null;
    }

    /**
     * @param  array<string, string>  $values
     */
    private function render(PdfModule $module, array $values, bool $flatten): string
    {
        $disk = Storage::disk('public');

        if (! $disk->exists($module->file_path)) {
            throw PdfFormException::missingTemplate($module->file_path);
        }

        if ($values === [] && ! $flatten) {
            return $disk->get($module->file_path);
        }

        $pdf = $this->open($disk->path($module->file_path));

        if ($values !== []) {
            $pdf->fillForm($values);
        }

        $flatten ? $pdf->flatten() : $pdf->needAppearances();

        $content = $pdf->toString();

        if ($content === false) {
            throw PdfFormException::pdftkFailed($pdf->getError());
        }

        return $content;
    }
}
