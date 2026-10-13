<?php

namespace App\Models;

use Database\Factories\FinancingQuoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Preventivo salvato da una simulazione di finanziamento.
 *
 * Il numero è progressivo per produttore: due produttori possono avere entrambi il preventivo 1.
 *
 * @property int $number
 * @property int $fornitore_id
 */
class FinancingQuote extends Model
{
    /** @use HasFactory<FinancingQuoteFactory> */
    use HasFactory;

    protected $connection = 'mysql';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'fornitore_id',
        'number',
        'contract_type',
        'sex',
        'birth_date',
        'employment_relationship',
        'hired_at',
        'starts_at',
        'duration_months',
        'assignable_quota',
        'requested_amount',
        'age_at_start',
        'seniority_months',
        'user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'hired_at' => 'date',
            'starts_at' => 'date',
            'duration_months' => 'integer',
            'assignable_quota' => 'decimal:2',
            'requested_amount' => 'decimal:2',
            'age_at_start' => 'integer',
            'seniority_months' => 'integer',
            'number' => 'integer',
        ];
    }

    public function fornitore(): BelongsTo
    {
        return $this->belongsTo(Fornitore::class, 'fornitore_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
