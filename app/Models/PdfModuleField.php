<?php

namespace App\Models;

use App\Enums\ModuleFormatter;
use App\Enums\ModuleSourceKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdfModuleField extends Model
{
    use HasFactory;

    protected $connection = 'mysql';

    protected $table = 'pdf_module_fields';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'pdf_module_id',
        'pdf_field_name',
        'pdf_field_type',
        'source_key',
        'formatter',
        'checkbox_on_value',
        'checkbox_when',
    ];

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
