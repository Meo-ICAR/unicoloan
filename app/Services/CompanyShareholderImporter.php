<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Client;
use App\Models\ClientRelation;

/**
 * Registra i soci di una societa' come Client (per codice fiscale / P.IVA), li lega alla societa' in client_relations
 * e, se manca, ne indica uno come legale rappresentante / amministratore.
 */
class CompanyShareholderImporter
{
    public function __construct(private readonly CodiceFiscaleDecoder $decoder) {}

    /**
     * @param  array<int, array{is_company: bool, name: ?string, first_name: ?string, tax_code: ?string, percent: ?float}>  $shareholders
     * @return array{created: int, linked: int}
     */
    public function import(Client $company, array $shareholders): array
    {
        $created = 0;
        $linked = 0;

        foreach ($shareholders as $holder) {
            $client = Client::query()->where('tax_code', $holder['tax_code'])->first();

            if ($client === null) {
                $client = Client::create(array_merge([
                    'name' => $holder['name'],
                    'first_name' => $holder['is_company'] ? null : $holder['first_name'],
                    'is_person' => ! $holder['is_company'],
                    'tax_code' => $holder['tax_code'],
                ], $holder['is_company'] ? [] : $this->birthData($holder['tax_code'])));
                $created++;
            }

            $relation = ClientRelation::query()->updateOrCreate(
                ['company_id' => (string) $company->getKey(), 'client_id' => $client->getKey()],
                [
                    'shares_percentage' => $holder['percent'],
                    'is_titolare' => ($holder['percent'] ?? 0) > 50,
                ],
            );

            $linked += $relation->wasRecentlyCreated ? 1 : 0;
        }

        $this->assignDefaultAdministrator($company);

        return ['created' => $created, 'linked' => $linked];
    }

    /**
     * Se la societa' non ha un legale rappresentante / amministratore, indica il socio persona fisica
     * titolare (quota > 50%) o, in mancanza, quello con la quota piu' alta. Non sovrascrive mai un valore esistente.
     */
    private function assignDefaultAdministrator(Client $company): void
    {
        if (filled($company->legal_representative_id)) {
            return;
        }

        $relation = ClientRelation::query()
            ->where('company_id', (string) $company->getKey())
            ->whereIn('client_id', Client::query()->where('is_person', true)->select('id'))
            ->orderByDesc('is_titolare')
            ->orderByDesc('shares_percentage')
            ->first();

        if ($relation !== null) {
            $company->forceFill(['legal_representative_id' => $relation->client_id])->save();
        }
    }

    /**
     * Salva sul cliente i dati del registro imprese e dell'ultimo bilancio.
     *
     * @param  array<string, mixed>  $company  blocco `company` di OpenApiCompanyService::fetchCompany()
     */
    public function syncRegistryData(Client $client, array $company): void
    {
        $client->forceFill(array_filter([
            'vat_number' => $company['vat'] ?? null,
            'legal_form' => $company['legal_form'] ?? null,
            'sdi_code' => $company['sdi_code'] ?? null,
            'activity_status' => $company['activity_status'] ?? null,
            'company_started_at' => $company['started_at'] ?? null,
            'share_capital' => $company['balance']['share_capital'] ?? null,
            'employees' => $company['balance']['employees'] ?? null,
            'turnover' => $company['balance']['turnover'] ?? null,
            'net_worth' => $company['balance']['net_worth'] ?? null,
            'balance_year' => $company['balance']['year'] ?? null,
        ], fn ($value) => filled($value)))->forceFill(['registry_updated_at' => now()])->save();
    }

    /**
     * Aggiorna la sede legale (sede principale) della societa' con l'indirizzo del registro imprese.
     *
     * @param  array<string, ?string>  $office
     */
    public function syncRegisteredOffice(Client $company, array $office): void
    {
        if (blank($office['address'] ?? null)) {
            return;
        }

        $branch = $company->branches()->where('is_main_office', true)->first() ?? new Branch(['name' => 'Sede legale', 'is_main_office' => true]);

        $branch->fill(array_filter($office, fn ($value) => filled($value)))->forceFill([
            'branchable_type' => $company->getMorphClass(),
            'branchable_id' => $company->getKey(),
            // Il tenant non ha più un valore predefinito nel database: una sede nuova prende l'azienda dell'app.
            'company_id' => $branch->company_id ?? app(CompanyResolver::class)->resolveId(),
        ])->save();
    }

    /**
     * @return array{birth_date?: string, sex?: string, birth_place?: string}
     */
    private function birthData(string $taxCode): array
    {
        $decoded = $this->decoder->decodeVerified($taxCode);

        return $decoded === null ? [] : [
            'birth_date' => $decoded['birth_date']->format('Y-m-d'),
            'sex' => $decoded['sex'],
            'birth_place' => $decoded['birth_place'],
        ];
    }
}
