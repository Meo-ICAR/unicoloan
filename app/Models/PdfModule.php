<?php

namespace App\Models;

use App\Enums\PdfModuleClientScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Unico\Core\Models\PdfModule as CorePdfModule;

/**
 * @property array<int, array{slot: string, role: string, page: int, x: float, y: float, width: float, height: float}>|null $signature_slots
 */
class PdfModule extends CorePdfModule
{
    use HasFactory;

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'tipi_prodotto' => 'array',
        'client_scope' => PdfModuleClientScope::class,
        'is_active' => 'boolean',
        'signature_slots' => 'array',
    ];

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function pdfModuleFields(): HasMany
    {
        return $this->hasMany(PdfModuleField::class, 'pdf_module_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Il modulo e' pertinente se il prodotto e' tra quelli indicati (o l'elenco e' vuoto)
     * e il tipo di cliente rientra nell'ambito del modulo.
     */
    public function appliesTo(?string $tipoProdotto, bool $isPerson): bool
    {
        $tipi = array_map('mb_strtolower', $this->tipi_prodotto ?? []);

        $productMatches = $tipi === []
            || ($tipoProdotto !== null && in_array(mb_strtolower($tipoProdotto), $tipi, true));

        $scope = $this->client_scope ?? PdfModuleClientScope::Entrambi;

        return $productMatches && $scope->matches($isPerson);
    }
}
