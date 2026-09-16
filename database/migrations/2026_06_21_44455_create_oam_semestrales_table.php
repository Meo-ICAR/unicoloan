<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('oam_semestrales', function (Blueprint $table) {
            $table->comment('Dati aggregati per il report OAM: Profilo Economico/Operativo Base');

            $table->id()->comment('ID univoco del record di report OAM semestrale');
            $table->uuid('company_id')->nullable()->index()->comment('ID Azienda (Tenant)');
            $table->char('period', 8)->nullable()->index()->comment('Anno e mese di riferimento del report');

            // Anagrafica e Convenzioni
            $table->string('numero_iscrizione_m510')->nullable()->comment('Numero iscrizione (M510)');
            $table->string('prodotto_creditizio')->nullable()->comment('PRODOTTO/O CREDITIZIO OGGETTO DELLA CONVENZIONE');
            $table->integer('intermediari_convenzionati')->default(0)->comment('N° intermediari convenzionati');
            $table->integer('intermediari_non_convenzionati')->default(0)->comment('N° intermediari NON convenzionati');

            // Volumi Pratiche
            $table->integer('pratiche_intermediate')->default(0)->comment('N° Pratiche intermediate per prodotto/servizio');
            $table->integer('pratiche_lavorazione')->default(0)->comment('N° Pratiche di finanziamento in lavorazione');

            // Valori di Erogato
            $table->decimal('erogato_lordo', 15, 2)->default(0)->comment('Montante lordo / Importo erogato per prodotto');
            $table->decimal('erogato_lavorazione', 15, 2)->default(0)->comment('Valore delle pratiche di finanziamento in lavorazione');

            // Provvigioni e Premi Ricevuti (Competenza)
            $table->decimal('provv_clientela', 15, 2)->default(0)->comment('TOTALE PROVVIGIONI RICONOSCIUTE DALLA CLIENTELA');
            $table->decimal('provv_istituto_comp', 15, 2)->default(0)->comment('TOTALE PROVVIGIONI RICONOSCIUTE DALL ISTITUTO EROGANTE');
            $table->decimal('premi_istituto_comp', 15, 2)->default(0)->comment('TOTALE PREMI RICONOSCIUTI DALL ISTITUTO EROGANTE');

            // Sezione PAY-IN (Provvigioni Assicurative)
            $table->decimal('payin_ass_banche', 15, 2)->default(0)->comment('(PAY-IN) PROVV. ASSICURATIVE - da banche/intermediari');
            $table->decimal('payin_ass_broker', 15, 2)->default(0)->comment('(PAY-IN) PROVV. ASSICURATIVE - da Broker');
            $table->decimal('payin_ass_broker_cap', 15, 2)->default(0)->comment('(PAY-IN) PROVV. ASSICURATIVE - da Broker Captive');

            // Sezione PAY-OUT (Costi Rete Distribuzione)
            $table->decimal('payout_rete_credito', 15, 2)->default(0)->comment('AMMONTARE PROVVIGIONI RETE - INTERMEDIAZIONE CREDITO');
            $table->decimal('payout_rete_ass_banche', 15, 2)->default(0)->comment('(PAY-OUT) PROVV. RETE ASSICURATIVA - da banche');
            $table->decimal('payout_rete_ass_broker', 15, 2)->default(0)->comment('(PAY-OUT) PROVV. RETE ASSICURATIVA - da Broker');
            $table->decimal('payout_rete_ass_broker_cap', 15, 2)->default(0)->comment('(PAY-OUT) PROVV. RETE ASSICURATIVA - da Broker Captive');

            // Rivalse e Retrocessioni
            $table->integer('num_rivalse')->default(0)->comment('N° RIVALSE AI SENSI DELL ART. 125 - SEXIES, DEL TUB');
            $table->decimal('importo_retrocesse', 15, 2)->default(0)->comment('AMMONTARE DELLE PROVVIGIONI RETROCESSE AL FINANZIATORE');

            // Campi di tracciamento inseriti per Filament 5
            $table->timestamps();

            // Chiave esterna sul Tenant (Companies)
            $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oam_semestrali');
    }
};
