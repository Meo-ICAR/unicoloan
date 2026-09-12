<?php

namespace App\Models\PROFORMA;

use App\Models\BlacklistClienteEmployee;
use App\Models\Branch;
use App\Models\Document;
use App\Models\OamCode;
use App\Models\ProvvigioniRule;
use App\Models\TipoprodottoSubConstraint;
use App\Models\Website;
use App\ValueObjects\OamSemester;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $cf
 * @property string|null $coge
 * @property string|null $codice
 * @property string|null $name
 * @property string|null $nome
 * @property string|null $piva
 * @property string|null $email
 * @property string|null $regione
 * @property string|null $citta
 * @property string $company_id
 * @property int|null $customertype_id
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Clienti extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $connection = 'mysql_proforma';

    protected $table = 'clientis';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    protected $orderBy = 'name';

    protected $orderDirection = 'asc';

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'abi',
        'abi_name',
        'stipulated_at',
        'dismissed_at',
        'type',
        'oam',
        'oam_name',
        'oam_at',
        'numero_iscrizione_rui',
        'ivass',
        'ivass_at',
        'ivass_name',
        'ivass_section',
        'mandate_number',
        'start_date',
        'end_date',
        'is_exclusive',
        'status',
        'notes',
        'principal_type',
        'submission_type',
        'cf',
        'website',
        'is_reported',
        'privacy_contact_email',
        'dpo_email',
        'coge',
        'codice',
        'name',
        'nome',
        'piva',
        'email',
        'regione',
        'citta',
        'company_id',
        'customertype_id',
        'is_active',
        'is_dummy',
    ];

    /**
     * Cast dei tipi di dato nativi (es. Date, Booleani).
     * Questo permette a Laravel e Filament di trattare correttamente i campi.
     */
    protected $casts = [
        'stipulated_at' => 'date',
        'dismissed_at' => 'date',
        'oam_at' => 'date',
        'ivass_at' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_exclusive' => 'boolean',
        'is_reported' => 'boolean',
        'is_active' => 'boolean',
        'is_dummy' => 'boolean',
    ];

    protected static function booted()
    {
        static::addGlobalScope('order_by_name', function ($builder) {
            $builder->orderBy('name');
        });
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function branches(): MorphMany
    {
        return $this->morphMany(Branch::class, 'branchable');
    }

    public function websites(): MorphMany
    {
        return $this->morphMany(Website::class, 'websiteable');
    }

    /**
     * Cerca un cliente per name, prende la piva, cerca il fornitore con la stessa piva
     * e flag is_dummy = false, e ritorna il campo nome del fornitore.
     */
    public static function getClienteNomeByName(string $name): ?string
    {
        $cliente = static::where('name', $name)->first();
        if (! $cliente) {
            return null;
        }
        $nome = $cliente->nome;
        if ($cliente->piva && $cliente->is_dummy) {
            $clientex = static::where('piva', $cliente->piva)
                ->where('is_dummy', false)
                ->first();
            $nome = $clientex?->nome;
        }

        return $nome;
    }

    public static function getClienteTipo(string $name): ?string
    {
        $cliente = static::where('nome', $name)->first();
        $tipo = $cliente->principal_type;

        return $tipo;
    }

    public static function getClienteSubmission(?string $name, string $prodotto): ?string
    {
        if (empty($name)) {
            return '--';
        }

        $cliente = static::where('nome', $name)->first();

        // Se il cliente non esiste, restituiamo un valore di fallback sicuro
        if (! $cliente) {
            return '--';
        }

        // Usiamo il costrutto match di PHP per mappare in modo pulito ed efficiente
        return match ($cliente->submission_type) {
            'accesso portale' => 'Accentrato',
            'inoltro' => 'Decentrato',
            'entrambi' => 'Modalita combinata',
            default => '--',
        };
    }

    public function oamCodes(): BelongsToMany
    {
        return $this
            ->belongsToMany(
                OamCode::class,  // Il modello correlato
                'unicooam.clienti_oam',  // La tabella pivot personalizzata
                'clienti_id',  // La chiave esterna di questa tabella nella pivot
                'oam_code_id'  // La chiave esterna del modello correlato nella pivot
            )
            ->where('is_active', true)  // <-- FILTRO: Mostra solo gli OamCode attivi
            ->withPivot('dal', 'al')  // Recupera i campi extra della tabella pivot
            ->withTimestamps();  // Gestisce automaticamente created_at e updated_at nella pivot
    }

    public function scopePerSemestreOam(Builder $query, OamSemester $semester): Builder
    {
        return $query->where('stipulated_at', '<=', $semester->end)
            ->where(function ($q) use ($semester) {
                $q->whereNull('dismissed_at')
                    ->orWhere('dismissed_at', '>=', $semester->start);
            });
    }

    public static function getisConvenzione(?string $name): ?bool
    {
        // Se il nome è nullo o vuoto, ritorniamo subito false
        if (empty($name)) {
            return false;
        }

        $cliente = static::where('abi_name', $name)->first();

        // Se non trovo il cliente, oppure se NON ha una data di stipula (null), non c'è convenzione
        if (! $cliente || empty($cliente->stipulated_at)) {
            return false;
        }

        $defaultSemester = OamSemester::getInBaseAlMeseCorrente();

        // Ora siamo sicuri che stipulated_at non è null
        $is_convenzione = $cliente->stipulated_at < $defaultSemester->end;

        // Se c'è una data di cessazione (non null), verifichiamo che sia successiva alla fine del semestre
        if (! empty($cliente->dismissed_at)) {
            $is_convenzione = $is_convenzione && $cliente->dismissed_at > $defaultSemester->end;
        }

        return $is_convenzione;
    }

    /**
     * Ottiene i sottoprodotti associati a questo prodotto finanziario.
     * Relazione 1 a Molti.
     */
    public function limits(): HasMany
    {
        // Specifichiamo la chiave esterna poiché il modello non si chiama 'TipoprodottoSub' standard
        return $this->hasMany(TipoprodottoSubConstraint::class, 'clienti_id');
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

    public function agentiBlacklistati()
    {
        return $this->belongsToMany(Fornitore::class, 'blacklist_clienti_fornitori', 'cliente_id', 'fornitore_id')
            ->withPivot(['motivo', 'data_inizio', 'data_fine'])
            ->withTimestamps();
    }

    /**
     * I blocchi verso dipendenti per questo istituto.
     *
     * A differenza di agentiBlacklistati(), qui non usiamo belongsToMany: `employees`
     * vive su una connessione/database diversa da `clientis`, quindi un join diretto
     * non è affidabile. Esponiamo invece le righe di blacklist; il chiamante può
     * risolvere i relativi Employee con `BlacklistClienteEmployee::employee()`.
     */
    public function dipendentiBlacklistati(): HasMany
    {
        return $this->hasMany(BlacklistClienteEmployee::class, 'cliente_id');
    }
}
