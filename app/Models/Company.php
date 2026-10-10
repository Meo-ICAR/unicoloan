<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Unico\Core\Models\Company as CoreCompany;

class Company extends CoreCompany
{
    use HasFactory;

    protected $orderBy = 'name';

    protected $orderDirection = 'asc';

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

    /**
     * Documenti che hanno l'azienda come soggetto (documentable). Il pacchetto usa lo stesso nome per tutti i documenti
     * del tenant: qui resta il significato storico dell'app, con un tipo di ritorno compatibile (HasMany).
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'documentable_id')->withAttributes(['documentable_type' => 'company']);
    }
}
