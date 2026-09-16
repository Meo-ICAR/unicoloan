<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sorgente di provenienza di un lead/cliente (App\Models\Client::leadSource()).
 *
 * @property int $id
 * @property string $name Nome identificativo della sorgente lead
 * @property string $type Macro-tipologia di canale (call_center, website, old_client, social_media, referral, other)
 * @property string|null $description Note o dettagli specifici sulla sorgente
 * @property string|null $utm_source Parametro UTM Source per lead da campagne web
 * @property string|null $utm_campaign Parametro UTM Campaign per lead da campagne web
 * @property bool $is_active Indica se la sorgente è attualmente attiva e selezionabile
 */
class LeadSource extends Model
{
    protected $table = 'lead_sources';

    protected $fillable = [
        'name',
        'type',
        'description',
        'utm_source',
        'utm_campaign',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * I client/lead acquisiti tramite questa sorgente.
     */
    public function clients(): HasMany
    {
        return $this->hasMany(Client::class, 'leadsource_id');
    }
}
