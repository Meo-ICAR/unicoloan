<?php

// app/Models/Fornitore.php

namespace App\Models\PROFORMA;

use App\Models\Branch;
use App\Models\ComplaintRegistry;
use App\Models\Document;
use App\Models\Website;
use App\ValueObjects\OamSemester;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Fornitore extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'mysql_proforma';

    protected $table = 'fornitoris';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $orderBy = 'name';

    protected $orderDirection = 'asc';

    protected $fillable = [
        'id',
        'name',
        'nome',
        'stipulated_at',
        'pec',
        'description',
        'email_private',
        'supervisor_type',
        'oam',
        'oam_at',
        'oam_name',
        'numero_iscrizione_rui',
        'ivass',
        'ivass_at',
        'dismissed_at',
        'ivass_name',
        'ivass_section',
        'type',
        'is_active',
        'is_art108',
        'company_branch_id',
        'coordinated_type',
        'coordinated_id',
        'user_id',
        'oam_dismissed_at',
        'welcome_bonus',
        'campagna',
        'available_at',
        'budget',
        'codice',
        'coge',
        'natoil',
        'indirizzo',
        'comune',
        'cap',
        'prov',
        'tel',
        'coordinatore',
        'piva',
        'cf',
        'nomecoge',
        'nomefattura',
        'email',
        'anticipo',
        'enasarco',
        'anticipo_residuo',
        'contributo',
        'contributo_description',
        'anticipo_description',
        'issubfornitore',
        'operatore',
        'iscollaboratore',
        'isdipendente',
        'regione',
        'citta',
        'company_id',
        'contributoperiodicita',
        'contributodalmese',
        'branch_id',
    ];

    /**
     * I cast dei tipi di dato nativi di Laravel 13.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Date
            'stipulated_at' => 'date',
            'oam_at' => 'date',
            'ivass_at' => 'date',
            'dismissed_at' => 'date',
            'oam_dismissed_at' => 'date',
            'available_at' => 'date',
            'natoil' => 'date',
            'contributodalmese' => 'date',
            'deleted_at' => 'datetime',
            // Booleani (tinyint)
            'is_active' => 'boolean',
            'is_art108' => 'boolean',
            'issubfornitore' => 'boolean',
            'iscollaboratore' => 'boolean',
            'isdipendente' => 'boolean',
            // Decimali
            'welcome_bonus' => 'decimal:2',
            'budget' => 'decimal:2',
            'anticipo' => 'decimal:2',
            'anticipo_residuo' => 'decimal:2',
            'contributo' => 'decimal:2',
            // Interi
            'company_branch_id' => 'integer',
            'coordinated_type' => 'integer',
            'coordinated_id' => 'integer',
            'user_id' => 'integer',
            'branch_id' => 'integer',
            'contributoperiodicita' => 'integer',
        ];
    }

    /**
     * Valori predefiniti per gli attributi del Model basati sul database SQL.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'supervisor_type' => 'no',
        'is_active' => true,
        'is_art108' => false,
        'enasarco' => 'plurimandatario',
        'contributo_description' => 'Contributo spese',
        'anticipo_description' => 'Anticipo attuale',
        'isdipendente' => false,
        'company_id' => '5c044917-15b3-4471-90c9-38061fcca754',
    ];

    /**
     * Cerca un cliente per name, prende la piva, cerca il fornitore con la stessa piva
     * e flag is_dummy = false, e ritorna il campo nome del fornitore.
     */
    public static function getFornitoreNomeByName(string $name): ?string
    {
        $cliente = static::where('name', $name)->first();
        $nome = $cliente?->nome;

        return $nome;
    }

    public function websites()
    {
        return $this->morphMany(Website::class, 'websiteable');
    }

    public function complaints(): MorphMany
    {
        return $this->morphMany(ComplaintRegistry::class, 'complainant');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function branches()
    {
        return $this->morphMany(Branch::class, 'branchable');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function scopePerSemestreOam(Builder $query, OamSemester $semester): Builder
    {
        return $query->where('stipulated_at', '<=', $semester->end)
            ->where(function ($q) use ($semester) {
                $q->whereNull('dismissed_at')
                    ->orWhere('dismissed_at', '>=', $semester->start);
            });
    }

    /**
     * Ottiene i sottoprodotti associati a questo prodotto finanziario.
     * Relazione 1 a Molti.
     */
    public function provvigioni(): HasMany
    {
        // Specifichiamo la chiave esterna poiché il modello non si chiama 'TipoprodottoSub' standard
        return $this->hasMany(ProvvigioniRule::class, 'clienti_id');
    }

    public function bancheBlacklist()
    {
        return $this->belongsToMany(Clienti::class, 'blacklist_clienti_fornitori', 'fornitore_id', 'cliente_id')
            ->withPivot(['motivo', 'data_inizio', 'data_fine'])
            ->withTimestamps();
    }

    /**
     * Helper per verificare rapidamente se l'agente è bloccato da una specifica banca.
     */
    public function isBlacklistedBy(string $clienteId): bool
    {
        return $this->bancheBlacklist()
            ->where('cliente_id', $clienteId)
            ->where(function ($query) {
                $query->whereNull('data_fine')
                    ->orWhere('data_fine', '>=', now());
            })
            ->exists();
    }
}
