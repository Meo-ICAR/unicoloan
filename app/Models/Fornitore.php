<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Unico\Core\Models\Fornitore as CoreFornitore;

/**
 * Agenti di commercio / Collaboratori (Fornitori di servizi).
 * Legge e scrive sulla connessione core (unico_core_test in locale).
 */
class Fornitore extends CoreFornitore
{
    use HasFactory;

    protected $orderBy = 'name';

    protected $orderDirection = 'asc';

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (self $fornitore) {
            if (empty($fornitore->company_id) && auth()->check()) {
                $fornitore->company_id = auth()->user()->profile?->company_id;
            }
        });
    }

    public static function getFornitoreNomeByName(string $name): ?string
    {
        return static::where('name', $name)->first()?->nome;
    }

    /**
     * Partite IVA degli agenti le cui pratiche questo fornitore puo' vedere nel portale agenti.
     *
     * @return array<int, string>
     */
    public function visibleAgentPivas(): array
    {
        return array_values(array_filter([$this->piva]));
    }

    public function websites(): MorphMany
    {
        return $this->morphMany(Website::class, 'websiteable');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function branches(): MorphMany
    {
        return $this->morphMany(Branch::class, 'branchable');
    }

    public function provvigioni(): HasMany
    {
        return $this->hasMany(ProvvigioniRule::class, 'fornitori_id');
    }

    public function blacklistRecords(): HasMany
    {
        return $this->hasMany(BlacklistClienteFornitore::class, 'fornitore_id');
    }

    public function isBlacklistedBy(string $clienteId): bool
    {
        return $this->blacklistRecords()
            ->attivi()
            ->where('cliente_id', $clienteId)
            ->exists();
    }
}
