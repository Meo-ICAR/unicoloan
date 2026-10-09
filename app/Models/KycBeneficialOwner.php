<?php

namespace App\Models;

use App\Enums\KycControlCriterion;
use App\Enums\KycPepStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Titolare effettivo dichiarato in un QAV.
 *
 * @property int $id
 * @property int $kyc_questionnaire_id
 * @property int $position
 * @property int $client_id
 * @property bool $is_verified
 */
class KycBeneficialOwner extends Model
{
    protected $fillable = [
        'kyc_questionnaire_id',
        'position',
        'client_id',
        'shares_percentage',
        'control_criterion',
        'pep_status',
        'declaration_signed_at',
        'document_id',
        'is_verified',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'shares_percentage' => 'decimal:2',
            'control_criterion' => KycControlCriterion::class,
            'pep_status' => KycPepStatus::class,
            'declaration_signed_at' => 'datetime',
            'is_verified' => 'boolean',
        ];
    }

    public function questionnaire(): BelongsTo
    {
        return $this->belongsTo(KycQuestionnaire::class, 'kyc_questionnaire_id');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
}
