<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Client extends Model
{
    /**
     * I campi che possono essere assegnati massivamente.
     * Excluded: id, created_at, updated_at
     *
     * @var array<int, string>
     */
    protected $connection = 'mysql_proforma';

    protected $table = 'proforma.clients';

    protected $fillable = [
        'company_id',
        'is_person',
        'name',
        'first_name',
        'tax_code',
        'vat_number',
        'email',
        'phone',
        'website',
        'is_pep',
        'client_type_id',
        'is_sanctioned',
        'is_remote_interaction',
        'general_consent_at',
        'privacy_policy_read_at',
        'consent_special_categories_at',
        'consent_sic_at',
        'consent_marketing_at',
        'consent_profiling_at',
        'status',
        'is_company',
        'is_lead',
        'leadsource_id',
        'acquired_at',
        'contoCOGE',
        'privacy_consent',
        'is_client',
        'subfornitori',
        'is_requiredApprovation',
        'is_approved',
        'is_anonymous',
        'blacklist_at',
        'blacklisted_by',
        'salary',
        'salary_quote',
        'is_art108',
        'is_consultant_gdpr',
        'privacy_contact_email',
        'dpo_email',
        'is_iso27001_certified',
        'is_dummy',
    ];

    /**
     * Il cast degli attributi ai tipi nativi.
     * Garantisce che i tinyint diventino booleani puri e gestisce i timestamp.
     *
     * @var array<string, string>
     */
    protected $casts = [
        // Booleani
        'is_person' => 'boolean',
        'is_pep' => 'boolean',
        'is_sanctioned' => 'boolean',
        'is_remote_interaction' => 'boolean',
        'is_company' => 'boolean',
        'is_lead' => 'boolean',
        'privacy_consent' => 'boolean',
        'is_client' => 'boolean',
        'is_requiredApprovation' => 'boolean',
        'is_approved' => 'boolean',
        'is_anonymous' => 'boolean',
        'is_art108' => 'boolean',
        'is_consultant_gdpr' => 'boolean',
        'is_iso27001_certified' => 'boolean',
        'is_dummy' => 'boolean',

        // decimali
        'salary' => 'decimal:2',
        'salary_quote' => 'decimal:2',

        // Date e Timestamp
        'general_consent_at' => 'datetime',
        'privacy_policy_read_at' => 'datetime',
        'consent_special_categories_at' => 'datetime',
        'consent_sic_at' => 'datetime',
        'consent_marketing_at' => 'datetime',
        'consent_profiling_at' => 'datetime',
        'acquired_at' => 'datetime',
        'blacklist_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // =========================================================================
    // RELAZIONI
    // =========================================================================

    /**
     * Relazione con il Tenant / Azienda proprietaria.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Relazione con la classificazione/tipo del cliente.
     */
    public function clientType(): BelongsTo
    {
        return $this->belongsTo(ClientType::class, 'client_type_id');
    }

    /**
     * Il client/lead di origine che ha generato/fornito questo contatto (Self-referencing).
     */
    public function leadSource(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'leadsource_id');
    }

    /**
     * I lead generati da questo specifico cliente (Self-referencing).
     */
    public function generatedLeads(): HasMany
    {
        return $this->hasMany(Client::class, 'leadsource_id');
    }

    /**
     * Se i documenti polimorfici sono collegati anche ai clienti (documentable_type = 'client').
     */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /**
     * Siti web collegati, rilevanti solo per clienti società (is_person = false).
     */
    public function websites(): MorphMany
    {
        return $this->morphMany(Website::class, 'websiteable');
    }

    /**
     * Sedi/filiali collegate, rilevanti solo per clienti società (is_person = false).
     */
    public function branches(): MorphMany
    {
        return $this->morphMany(Branch::class, 'branchable');
    }

    public function companyRelations()
    {
        return $this->hasMany(ClientRelation::class, 'company_id');
    }

    public function personRelations()
    {
        return $this->hasMany(ClientRelation::class, 'client_id');
    }

    public function clientMandates(): HasMany
    {
        return $this->hasMany(ClientMandate::class);
    }

    public function clientPratiches(): HasMany
    {
        return $this->hasMany(Pratica::class, 'codice_fiscale', 'tax_code');
    }

    public function activeMandates(): HasMany
    {
        return $this->clientMandates()->where('stato', 'attivo');
    }

    public function hasActiveMandates(): bool
    {
        return $this->activeMandates()->exists();
    }

    public function getLatestMandate()
    {
        return $this->clientMandates()->latest()->first();
    }

    public function getTotalMandateAmount(): float
    {
        return $this
            ->clientMandates()
            ->whereNotNull('importo_richiesto_mandato')
            ->sum('importo_richiesto_mandato');
    }
}
