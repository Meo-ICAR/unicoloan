<?php

namespace App\Models;

use App\Enums\KycActivityLocation;
use App\Enums\KycActivitySector;
use App\Enums\KycCoverage;
use App\Enums\KycEconomicActivity;
use App\Enums\KycExecutorLink;
use App\Enums\KycFinancingNature;
use App\Enums\KycGeographicArea;
use App\Enums\KycIncomeBand;
use App\Enums\KycLegalNature;
use App\Enums\KycPepStatus;
use App\Enums\KycRiskLevel;
use App\Enums\KycStatus;
use App\Enums\KycWealthBand;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Questionario di adeguata verifica (QAV) di un cliente.
 *
 * @property int $id
 * @property int $client_id
 * @property KycStatus $status
 */
class KycQuestionnaire extends Model
{
    use HasFactory;

    /**
     * @var array<string, string>
     */
    public const FIELD_LABELS = [
        'pep_status' => 'Condizione PEP',
        'economic_activity' => 'Attività economica',
        'activity_sector' => 'Settore di attività',
        'activity_location' => 'Luogo di svolgimento dell\'attività',
        'financing_nature' => 'Natura del finanziamento',
        'financing_purpose' => 'Scopo del finanziamento',
        'income_band' => 'Reddito annuo lordo',
        'wealth_band' => 'Patrimonio',
        'risk_level' => 'Livello di rischio',
        'legal_nature' => 'Natura giuridica',
        'geographic_area' => 'Area geografica',
        'executor_client_id' => 'Esecutore',
        'executor_link' => 'Legame con l\'esecutore',
        'executor_pep_status' => 'Condizione PEP dell\'esecutore',
    ];

    /**
     * @var array<int, string>
     */
    public const PERSON_REQUIRED = [
        'pep_status', 'economic_activity', 'activity_sector', 'activity_location',
        'financing_nature', 'financing_purpose', 'income_band', 'wealth_band', 'risk_level',
    ];

    /**
     * @var array<int, string>
     */
    public const COMPANY_REQUIRED = [
        'legal_nature', 'geographic_area', 'financing_purpose', 'executor_client_id',
        'executor_link', 'executor_pep_status', 'risk_level',
    ];

    protected $connection = 'mysql';

    protected $fillable = [
        'client_id',
        'client_mandate_id',
        'pratica_id',
        'document_id',
        'pep_status',
        'financing_purpose',
        'risk_level',
        'status',
        'compiled_at',
        'verified_by',
        'verified_at',
        'notes',
        'economic_activity',
        'activity_sector',
        'activity_location',
        'financing_nature',
        'income_band',
        'wealth_band',
        'acts_for_third_party',
        'legal_nature',
        'geographic_area',
        'executor_client_id',
        'executor_link',
        'executor_pep_status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pep_status' => KycPepStatus::class,
            'risk_level' => KycRiskLevel::class,
            'status' => KycStatus::class,
            'compiled_at' => 'datetime',
            'verified_at' => 'datetime',
            'economic_activity' => KycEconomicActivity::class,
            'activity_sector' => KycActivitySector::class,
            'activity_location' => KycActivityLocation::class,
            'financing_nature' => KycFinancingNature::class,
            'income_band' => KycIncomeBand::class,
            'wealth_band' => KycWealthBand::class,
            'acts_for_third_party' => 'boolean',
            'legal_nature' => KycLegalNature::class,
            'geographic_area' => KycGeographicArea::class,
            'executor_link' => KycExecutorLink::class,
            'executor_pep_status' => KycPepStatus::class,
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function executor(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'executor_client_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function beneficialOwners(): HasMany
    {
        return $this->hasMany(KycBeneficialOwner::class)->orderBy('position');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', KycStatus::Approved);
    }

    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('verified_at')->orderByDesc('id');
    }

    public function coverage(): KycCoverage
    {
        $expiresAt = $this->document?->expires_at;

        return $expiresAt !== null && $expiresAt->lt(today()) ? KycCoverage::Expired : KycCoverage::Complete;
    }

    /**
     * @return Collection<int, KycCoverage>
     */
    public static function coverageByClient(): Collection
    {
        return static::approved()->with('document')->latestFirst()->get()
            ->unique('client_id')
            ->mapWithKeys(fn (self $q) => [$q->client_id => $q->coverage()]);
    }

    /**
     * Requisiti ancora mancanti per approvare il QAV (vuoto = approvabile).
     *
     * @return array<int, string>
     */
    public function missingRequirements(): array
    {
        $isPerson = (bool) $this->client?->is_person;
        $required = $isPerson ? self::PERSON_REQUIRED : self::COMPANY_REQUIRED;

        $missing = collect($required)
            ->filter(fn (string $field) => blank($this->{$field}))
            ->map(fn (string $field) => self::FIELD_LABELS[$field])
            ->values()
            ->all();

        $owners = $this->beneficialOwners;

        if (! $isPerson || $this->acts_for_third_party) {
            if ($owners->isEmpty()) {
                $missing[] = 'Almeno un titolare effettivo';
            } else {
                if ($owners->contains(fn (KycBeneficialOwner $o) => blank($o->control_criterion) || blank($o->pep_status))) {
                    $missing[] = 'Criterio e PEP di ogni titolare effettivo';
                }
                if ($owners->contains(fn (KycBeneficialOwner $o) => ! $o->is_verified)) {
                    $missing[] = 'Verifica di tutti i titolari effettivi';
                }
            }
        }

        return $missing;
    }

    /**
     * @return array<int, string>
     */
    public function warnings(): array
    {
        return $this->beneficialOwners->count() > 3
            ? ['Il QAV stampa al massimo 3 titolari effettivi: gli altri vanno allegati a parte (più di 3 dichiarati).']
            : [];
    }
}
