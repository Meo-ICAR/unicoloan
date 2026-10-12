<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Concerns\GeneratesPlichi;
use App\Models\PROFORMA\Clienti;
use App\Models\PROFORMA\Provvigione;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Unico\Core\Models\Pratica as CorePratica;
use Unico\Core\Models\PraticaStato as CorePraticaStato;
use Unico\Core\Models\TipoProdotto as CoreTipoProdotto;

/**
 * Pratica di finanziamento. Tabella `pratiches` su unico-core.
 */
class Pratica extends CorePratica
{
    use GeneratesPlichi;

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'nrate' => 'integer',
        ]);
    }

    public ?string $pendingStatusNote = null;

    /** @var array{creating: bool, from: ?string, dirty: bool} */
    private array $statusLogContext = ['creating' => false, 'from' => null, 'dirty' => false];

    protected function isGuardableColumn($key): bool
    {
        return in_array($key, ['stato_pratica', 'tipo_prodotto'], true) ? true : parent::isGuardableColumn($key);
    }

    public function getAttribute($key)
    {
        return match ($key) {
            'stato_pratica' => $this->stato?->codice,
            'tipo_prodotto' => $this->tipoprodotto?->tipo_prodotto,
            default => parent::getAttribute($key),
        };
    }

    public function setAttribute($key, $value)
    {
        if ($key === 'stato_pratica') {
            $this->attributes['pratica_stato_id'] = $this->resolvePraticaStatoId($value);
            $this->unsetRelation('stato');
            $this->unsetRelation('praticaStato');

            return $this;
        }

        if ($key === 'tipo_prodotto') {
            $this->attributes['tipoprodotto_id'] = blank($value)
                ? null
                : CoreTipoProdotto::firstOrCreate(['tipo_prodotto' => $value], ['name' => $value])->getKey();
            $this->unsetRelation('tipoprodotto');

            return $this;
        }

        return parent::setAttribute($key, $value);
    }

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (self $pratica): void {
            if (blank($pratica->upload_at)) {
                $pratica->upload_at = now();
            }
        });

        static::saving(function (self $pratica): void {
            $fromId = $pratica->getOriginal('pratica_stato_id');
            $pratica->statusLogContext = [
                'creating' => ! $pratica->exists,
                'from' => $fromId ? CorePraticaStato::query()->find($fromId)?->codice : null,
                'dirty' => $pratica->isDirty('pratica_stato_id'),
            ];

            if ($pratica->rejected_at === null && $pratica->isDirty('pratica_stato_id') && $pratica->isRejectedState()) {
                $pratica->rejected_at = now();
            }
        });

        static::saved(function (self $pratica): void {
            $pratica->logStatusChange();
        });
    }

    private function logStatusChange(): void
    {
        $note = filled($this->pendingStatusNote) ? trim($this->pendingStatusNote) : null;
        $this->pendingStatusNote = null;

        $current = trim((string) $this->stato_pratica);
        $previous = $this->statusLogContext['creating'] ? null : $this->statusLogContext['from'];
        $changed = $current !== '' && ($this->statusLogContext['creating'] || $this->statusLogContext['dirty']);

        if (! $changed && $note === null) {
            return;
        }

        $user = auth()->user();

        PraticaStatusHistory::query()->create([
            'pratica_id' => $this->getKey(),
            'status_from' => $changed ? $previous : $current,
            'status_to' => $current !== '' ? $current : '-',
            'changed_at' => now(),
            'source' => $user === null ? 'sistema' : ($user->role === UserRole::AGENT->value ? 'portale' : 'manuale'),
            'notes' => $note,
            'user_id' => $user?->getKey(),
        ]);
    }

    public function isRejectedState(): bool
    {
        return (bool) ($this->stato?->is_rejected);
    }

    private function resolvePraticaStatoId(mixed $value): ?int
    {
        if (blank($value)) {
            return null;
        }

        $code = trim((string) $value);
        $stato = CorePraticaStato::query()->whereRaw('LOWER(codice) = ?', [mb_strtolower($code)])->first();

        if ($stato === null) {
            $stato = CorePraticaStato::query()->create([
                'codice' => $code,
                'name' => $code,
                'is_rejected' => $this->catalogIsRejected($code),
            ]);
        } elseif (! $stato->is_rejected && $this->catalogIsRejected($code)) {
            $stato->forceFill(['is_rejected' => true])->saveQuietly();
        }

        return (int) $stato->getKey();
    }

    private function catalogIsRejected(string $code): bool
    {
        return PraticaStati::query()
            ->whereRaw('LOWER(stato_pratica) = ?', [mb_strtolower($code)])
            ->where('isrejected', true)
            ->exists();
    }

    public function praticaClients(): HasMany
    {
        return $this->hasMany(PraticaClient::class, 'pratica_id', 'id');
    }

    public function isCessioneDelega(): bool
    {
        return (bool) preg_match('/cession|deleg/i', (string) $this->tipo_prodotto);
    }

    public function agente()
    {
        return $this->belongsTo(Fornitore::class, 'partita_iva_agente', 'piva');
    }

    public function istituto()
    {
        return $this->belongsTo(Clienti::class, 'denominazione_banca', 'name');
    }

    public function oamCode()
    {
        return $this->belongsTo(OamCode::class, 'tipo_prodotto', 'tipo_prodotto');
    }

    public function stato(): BelongsTo
    {
        return $this->belongsTo(CorePraticaStato::class, 'pratica_stato_id');
    }

    public function annullato(): bool
    {
        return (bool) ($this->stato?->is_rejected ?? false);
    }

    public function provvigioni()
    {
        return $this->hasMany(Provvigione::class, 'id_pratica', 'id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(PraticaStatusHistory::class, 'pratica_id')->orderBy('changed_at', 'desc');
    }

    public function clienteAnagrafica(): ?Client
    {
        $code = Str::upper(trim((string) $this->codice_fiscale));

        if ($code === '') {
            return null;
        }

        return Client::query()->where('tax_code', $code)->orWhere('vat_number', $code)->first();
    }

    public function kycQuestionnaires(): HasMany
    {
        return $this->hasMany(KycQuestionnaire::class, 'pratica_id');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function requisitiOperativi(): HasMany
    {
        return $this->hasMany(PraticaRequisitoOperativo::class, 'requestable_id')
            ->withAttributes(['requestable_type' => 'pratica']);
    }

    public function generaRequisitiDaProdotto(): void
    {
        if (! $this->tipoprodotto_sub_id) {
            return;
        }

        $regole = RequisitoTipoFinanziamento::where('tipoprodotto_sub_id', $this->tipoprodotto_sub_id)
            ->orderBy('sort_order')
            ->get();

        foreach ($regole as $regola) {
            $this->requisitiOperativi()->firstOrCreate(
                ['document_type_id' => $regola->document_type_id],
                [
                    'status' => 'da_richiedere',
                    'requested_at' => null,
                    'is_required' => $regola->is_required,
                ]
            );
        }
    }

    public function haRequisitiObbligatoriIncompleti(): bool
    {
        return $this->requisitiOperativi()
            ->where('is_required', true)
            ->where('status', '!=', 'approvato')
            ->exists();
    }

    public function getRequisitiObbligatoriMancanti(): Collection
    {
        return $this->requisitiOperativi()
            ->with('requisito')
            ->where('is_required', true)
            ->where('status', '!=', 'approvato')
            ->get();
    }

    public function getPercentualeCompletamentoRequisitiAttribute(): int
    {
        $totale = $this->requisitiOperativi()->count();

        if ($totale === 0) {
            return 100;
        }

        $completati = $this->requisitiOperativi()
            ->where('status', 'approvato')
            ->count();

        return (int) round(($completati / $totale) * 100);
    }

    public function puoAvanzareAStato(PraticaStato $nuovoStato): bool
    {
        if (in_array($nuovoStato->codice, ['fascicolo_completo', 'deliberata', 'approvata'])) {
            return ! $this->haRequisitiObbligatoriIncompleti();
        }

        return true;
    }
}
