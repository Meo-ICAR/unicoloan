<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\OamSemestrale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OamSemestrale>
 */
class OamSemestraleFactory extends Factory
{
    protected $model = OamSemestrale::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $prodotti = [
            'Mutuo Ipotecario',
            'Cessione del Quinto',
            'TFS/TFR Liquidazione',
            'Credito al Consumo',
            'Segnalazione Mutuo',
        ];

        return [
            'company_id' => Company::factory(),
            'period' => fake()->randomElement(['202501', '202507', '202401', '202407']),
            'prodotto_creditizio' => fake()->randomElement($prodotti),
            'intermediari_convenzionati' => fake()->numberBetween(0, 10),
            'intermediari_non_convenzionati' => fake()->numberBetween(0, 5),
            'pratiche_intermediate' => fake()->numberBetween(0, 100),
            'pratiche_lavorazione' => fake()->numberBetween(0, 50),
            'erogato_lordo' => fake()->randomFloat(2, 0, 2000000),
            'erogato_lavorazione' => fake()->randomFloat(2, 0, 500000),
            'provv_clientela' => fake()->randomFloat(2, 0, 5000),
            'provv_istituto_comp' => fake()->randomFloat(2, 0, 10000),
            'premi_istituto_comp' => fake()->randomFloat(2, 0, 2000),
            'payin_ass_banche' => fake()->randomFloat(2, 0, 3000),
            'payin_ass_broker' => fake()->randomFloat(2, 0, 2000),
            'payin_ass_broker_cap' => fake()->randomFloat(2, 0, 1000),
            'payout_rete_credito' => fake()->randomFloat(2, 0, 8000),
            'payout_rete_ass_banche' => fake()->randomFloat(2, 0, 1000),
            'payout_rete_ass_broker' => fake()->randomFloat(2, 0, 500),
            'payout_rete_ass_broker_cap' => fake()->randomFloat(2, 0, 200),
            'num_rivalse' => fake()->numberBetween(0, 5),
            'importo_retrocesse' => fake()->randomFloat(2, 0, 1000),
        ];
    }
}
