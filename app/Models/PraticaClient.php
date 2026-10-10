<?php

namespace App\Models;

use App\Enums\PraticaClientRole;
use App\Models\PROFORMA\Pratica;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Soggetto (client) collegato a una pratica con un ruolo: richiedente, coobbligato o garante.
 *
 * @property int $id
 * @property string $pratica_id
 * @property int $client_id
 * @property PraticaClientRole $role
 */
class PraticaClient extends Model
{
    use HasFactory;

    protected $connection = 'mysql_proforma';

    protected $table = 'pratica_clients';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'pratica_id',
        'client_id',
        'role',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => PraticaClientRole::class,
        ];
    }

    public function pratica(): BelongsTo
    {
        return $this->belongsTo(Pratica::class, 'pratica_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
}
