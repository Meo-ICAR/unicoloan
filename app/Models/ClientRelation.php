<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $company_id
 * @property int $client_id
 * @property float|null $shares_percentage
 * @property bool $is_titolare
 * @property int|null $client_type_id
 * @property Carbon|null $data_inizio_ruolo
 * @property Carbon|null $data_fine_ruolo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ClientRelation extends Model
{
    use HasFactory;

    protected $table = 'client_relations';

    protected $fillable = [
        'company_id',
        'client_id',
        'shares_percentage',
        'is_titolare',
        'client_type_id',
        'data_inizio_ruolo',
        'data_fine_ruolo',
    ];

    protected $casts = [
        'shares_percentage' => 'decimal:2',
        'is_titolare' => 'boolean',
        'data_inizio_ruolo' => 'date',
        'data_fine_ruolo' => 'date',
    ];

    /**
     * Relazione con la Società (Azienda).
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id', 'id');
    }

    /**
     * Relazione con il Cliente (Persona Fisica).
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id', 'id');
    }

    /**
     * Relazione con la tipologia di cliente (Privacy/Ruolo).
     */
    public function clientType(): BelongsTo
    {
        return $this->belongsTo(ClientType::class, 'client_type_id', 'id');
    }
}
