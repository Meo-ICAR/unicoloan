<?php

namespace Database\Seeders;

use App\Models\PROFORMA\Clienti;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TipoProdottoSubConstraintsSeeder extends Seeder
{
    /**
     * Popola i vincoli di fattibilità per alcuni sottoprodotti seminati da
     * TipoProdottoSubSeeder. `tipoprodotto_sub_id` viene risolto in base al
     * `code` del sottoprodotto invece di un ID fisso, perché l'ID reale
     * dipende dall'ordine di inserimento effettivo nella tabella (non più
     * garantito da un truncate). `clienti_id` è NOT NULL a livello di schema
     * (un vincolo è sempre imposto da una banca specifica): come esempio
     * viene usata la prima banca (Clienti) esistente. Non inserisce due
     * volte lo stesso vincolo.
     */
    public function run(): void
    {
        $bancaId = Clienti::query()->value('id');

        if ($bancaId === null) {
            $this->command?->warn('Nessuna Clienti (banca) trovata: TipoProdottoSubConstraintsSeeder saltato.');

            return;
        }

        $now = now();

        $constraints = [
            // Cessione del Quinto Stipendio (CQS)
            [
                'clienti_id' => $bancaId,
                'tipoprodotto_id' => 7,
                'sub_code' => 'CQS',
                'min_age' => 18,
                'max_age_at_maturity' => 65,
                'min_amount' => 3000.00,
                'max_amount' => 75000.00,
                'min_duration_months' => 24,
                'max_duration_months' => 120,
                'min_employment_months' => 6,
                'max_debt_to_income_ratio' => 20.00, // Massimo un quinto dello stipendio
                'max_ltv_percentage' => null,
                'allowed_employment_types' => json_encode(['dipendente_pubblico', 'dipendente_privato']),
                'additional_rules_json' => json_encode([
                    'crif_allowed' => true, // La CQS spesso ignora le segnalazioni in CRIF
                    'requires_tfr' => true,
                ]),
                'additional_notes' => 'Vincoli standard CQS. Richiesta assunzione a tempo indeterminato.',
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // Cessione del Quinto Pensione (CQP)
            [
                'clienti_id' => $bancaId,
                'tipoprodotto_id' => 7,
                'sub_code' => 'CQP',
                'min_age' => 60,
                'max_age_at_maturity' => 85, // Età massima più elevata per i pensionati
                'min_amount' => 3000.00,
                'max_amount' => 50000.00,
                'min_duration_months' => 24,
                'max_duration_months' => 120,
                'min_employment_months' => null,
                'max_debt_to_income_ratio' => 20.00,
                'max_ltv_percentage' => null,
                'allowed_employment_types' => json_encode(['pensionato']),
                'additional_rules_json' => json_encode([
                    'crif_allowed' => true,
                    'inps_convenzionata' => true,
                ]),
                'additional_notes' => 'CQP per pensionati INPS/ex-INPDAP. Controllare quota cedibile.',
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // Mutuo Acquisto
            [
                'clienti_id' => $bancaId,
                'tipoprodotto_id' => 13,
                'sub_code' => 'MUT_ACQ',
                'min_age' => 18,
                'max_age_at_maturity' => 75,
                'min_amount' => 50000.00,
                'max_amount' => 500000.00,
                'min_duration_months' => 120,
                'max_duration_months' => 360, // 30 anni
                'min_employment_months' => 12,
                'max_debt_to_income_ratio' => 33.33, // Rata/reddito massimo circa 1/3
                'max_ltv_percentage' => 80.00, // Limite standard LTV
                'allowed_employment_types' => json_encode(['indeterminato', 'autonomo']),
                'additional_rules_json' => json_encode([
                    'requires_co_signer' => false,
                    'blocked_ateco_codes' => [],
                ]),
                'additional_notes' => 'LTV all\'80%. Deroghe per LTV 100% richiedono garanzia Consap (da configurare a parte).',
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // Prestito Personale
            [
                'clienti_id' => $bancaId,
                'tipoprodotto_id' => 16,
                'sub_code' => 'PP',
                'min_age' => 18,
                'max_age_at_maturity' => 75,
                'min_amount' => 1500.00,
                'max_amount' => 30000.00,
                'min_duration_months' => 12,
                'max_duration_months' => 84,
                'min_employment_months' => 12,
                'max_debt_to_income_ratio' => 35.00,
                'max_ltv_percentage' => null,
                'allowed_employment_types' => json_encode(['indeterminato', 'autonomo', 'pensionato']),
                'additional_rules_json' => json_encode([
                    'crif_allowed' => false, // Zero tolleranza per segnalazioni sui prestiti personali
                ]),
                'additional_notes' => 'Prestito personale chirografario puro. Valutazione rigida sul merito creditizio.',
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // Anticipo TFS (Trattamento Fine Servizio)
            [
                'clienti_id' => $bancaId,
                'tipoprodotto_id' => 18,
                'sub_code' => 'TFS_ANT',
                'min_age' => 55,
                'max_age_at_maturity' => 75,
                'min_amount' => 10000.00,
                'max_amount' => 150000.00,
                'min_duration_months' => 12,
                'max_duration_months' => 180,
                'min_employment_months' => null, // Non rilevante, il cliente è già in quiescenza o in uscita
                'max_debt_to_income_ratio' => null, // Rientro tramite Tassazione agevolata/Inps, non rata mensile fissa
                'max_ltv_percentage' => null,
                'allowed_employment_types' => json_encode(['pensionato_pubblico']),
                'additional_rules_json' => json_encode([
                    'requires_certificato_quantificazione' => true,
                ]),
                'additional_notes' => 'Finanziamento erogato in un\'unica soluzione garantito dal TFS maturato.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        foreach ($constraints as $constraint) {
            $subCode = $constraint['sub_code'];
            unset($constraint['sub_code']);

            $subId = DB::connection('mysql_proforma')
                ->table('tipoprodotto_sub')
                ->where('code', $subCode)
                ->value('id');

            if ($subId === null) {
                continue;
            }

            $constraint['tipoprodotto_sub_id'] = $subId;

            $alreadyExists = DB::table('tipoprodotto_sub_constraints')
                ->where('tipoprodotto_sub_id', $subId)
                ->where('clienti_id', $bancaId)
                ->exists();

            if ($alreadyExists) {
                continue;
            }

            DB::table('tipoprodotto_sub_constraints')->insert($constraint);
        }
    }
}
