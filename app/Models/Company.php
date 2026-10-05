<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory, HasUuids;

    /**
     * I campi che possono essere assegnati in massa (Mass Assignment).
     *
     * @var array<int, string>
     */
    protected $connection = 'mysql';

    protected $orderBy = 'name';

    protected $orderDirection = 'asc';

    protected $fillable = [
        'name',
        'vat_number',
        'vat_name',
        'oam',
        'oam_at',
        'oam_name',
        'numero_iscrizione_rui',
        'ivass',
        'ivass_at',
        'ivass_name',
        'ivass_section',
        'sponsor',
        'company_type',
        'page_header',
        'page_footer',
    ];

    /**
     * I cast dei tipi di dato per gli attributi.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'oam_at' => 'date',
        'ivass_at' => 'date',
    ];

    public function branches()
    {
        return $this->morphMany(Branch::class, 'branchable');
    }

    public function websites()
    {
        return $this->morphMany(Website::class, 'websiteable');
    }

    public function companyRoles(): HasMany
    {
        // Collega l'azienda a molti CompanyRole
        return $this->hasMany(CompanyRole::class);
    }

    public function mailAccount(): MorphOne
    {
        return $this->morphOne(MailAccount::class, 'mailable');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}
