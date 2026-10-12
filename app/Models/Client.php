<?php

namespace App\Models;

use App\Enums\KycCoverage;
use App\Models\Concerns\GeneratesPlichi;
use App\Models\PROFORMA\Pratica;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Unico\Core\Models\Client as CoreClient;

/**
 * Clienti finali (persone fisiche e giuridiche) con dati KYC e consensi privacy.
 * Legge e scrive sulla connessione 'core' -> database unico_core_test.
 */
class Client extends CoreClient
{
    use GeneratesPlichi;

    // =========================================================================
    // RELAZIONI
    // =========================================================================

    /**
     * I finanziamenti di terzi del cliente: pratiche "non nostre" (`is_notowned`) con lo stesso
     * codice fiscale, usate ad esempio per la richiesta di conteggio estintivo.
     */
    public function thirdPartyFinancings(): HasMany
    {
        return $this->hasMany(Pratica::class, 'codice_fiscale', 'tax_code')
            ->where('is_notowned', true)
            ->orderByDesc('data_inserimento_pratica');
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

    public function kycQuestionnaires(): HasMany
    {
        return $this->hasMany(KycQuestionnaire::class);
    }

    public function currentKyc(): ?KycQuestionnaire
    {
        return $this->kycQuestionnaires()->approved()->withDocument()->latestFirst()->first();
    }

    public function kycCoverage(): KycCoverage
    {
        return $this->currentKyc()?->coverage() ?? KycCoverage::Missing;
    }
}
