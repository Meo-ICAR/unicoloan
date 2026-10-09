<?php

namespace App\Models;

use App\Enums\KycActivityLocation;
use App\Enums\KycActivitySector;
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
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}
