<?php

namespace App\Models;

use App\Enums\ApiCallStatus;
use App\Enums\ApiProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Chiamata a un web service esterno, con esito e costo; il soggetto interrogato e' un record qualsiasi (morph).
 *
 * @property ApiProvider $provider
 * @property ApiCallStatus $status
 * @property string $cost
 */
class ApiCall extends Model
{
    use HasFactory;

    protected $connection = 'mysql';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'provider',
        'operation',
        'subject_type',
        'subject_id',
        'user_id',
        'status',
        'http_status',
        'duration_ms',
        'cost',
        'currency',
        'request',
        'response',
        'summary',
        'reference',
        'error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => ApiProvider::class,
            'status' => ApiCallStatus::class,
            'cost' => 'decimal:4',
            'request' => 'array',
            'response' => 'array',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
