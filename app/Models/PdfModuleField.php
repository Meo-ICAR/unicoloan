<?php

namespace App\Models;

use Unico\Core\Pdf\ModuleFormatter;
use App\Enums\ModuleSourceKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Unico\Core\Models\PdfModuleField as CorePdfModuleField;

class PdfModuleField extends CorePdfModuleField
{
    use HasFactory;

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'source_key' => ModuleSourceKey::class,
        'formatter' => ModuleFormatter::class,
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(PdfModule::class, 'pdf_module_id');
    }

    public function isCheckbox(): bool
    {
        return $this->pdf_field_type === 'checkbox';
    }

    /**
     * Le chiavi dati da cui dipende la compilazione del campo.
     *
     * @return array<int, ModuleSourceKey>
     */
    public function referencedKeys(): array
    {
        $keys = [];

        if ($this->source_key instanceof ModuleSourceKey) {
            $keys[] = $this->source_key;
        }

        if (filled($this->checkbox_when)) {
            $when = $this->parseCheckboxWhen()['key'];

            if ($when !== null) {
                $keys[] = $when;
            }
        }

        return $keys;
    }

    /**
     * Interpreta `checkbox_when` nel formato `[!]chiave[=valore]`.
     *
     * @return array{negate: bool, key: ModuleSourceKey|null, value: string|null}
     */
    public function parseCheckboxWhen(): array
    {
        $when = (string) $this->checkbox_when;
        $negate = str_starts_with($when, '!');
        [$name, $value] = array_pad(explode('=', ltrim($when, '!'), 2), 2, null);

        return ['negate' => $negate, 'key' => ModuleSourceKey::tryFrom($name), 'value' => $value];
    }
}
