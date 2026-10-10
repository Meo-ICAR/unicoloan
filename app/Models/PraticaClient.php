<?php

namespace App\Models;

use App\Enums\PraticaClientRole;
use App\Models\PROFORMA\Pratica;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Unico\Core\Models\PraticaClient as CorePraticaClient;

/**
 * Soggetto (client) collegato a una pratica con un ruolo: richiedente, coobbligato o garante.
 *
 * @property int $id
 * @property string $pratica_id
 * @property int $client_id
 * @property PraticaClientRole $role
 */
class PraticaClient extends CorePraticaClient
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'role' => PraticaClientRole::class,
        ]);
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
