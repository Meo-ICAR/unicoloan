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
        Schema::create('oam_pratiches', function (Blueprint $table) {
            $table->comment('Dati aggregati per il report OAM: Profilo Economico/Operativo Base');

            $table->id()->comment('ID univoco del record di report OAM');
            $table->uuid('company_id')->nullable()->index()->comment('ID Azienda (Tenant)');
            $table->char('period', 8)->nullable()->index()->comment('Anno e mese di riferimento del report');

            // Anagrafica Prodotto
            $table->string('riga')->nullable()->comment('riga');
            $table->string('prodotto_creditizio')->nullable()->comment('PRODOTTO/O CREDITIZIO OGGETTO DELLA CONVENZIONE');

            // Intermediari
            $table->integer('intermediari_convenzionati')->default(0)->comment('N° intermediari convenzionati');
            $table->integer('intermediari_non_convenzionati')->default(0)->comment('N° intermediari NON convenzionati');

            // Pratiche (Quantità)
            $table->integer('pratiche_intermediate')->default(0)->comment('N° Pratiche intermediate per prodotto/servizio');
            $table->integer('pratiche_lavorazione')->default(0)->comment('N° Pratiche di finanziamento in lavorazione');

            // Erogato (Importi)
            $table->decimal('erogato_lordo', 15, 2)->default(0)->comment('Montante lordo / Importo erogato per prodotto');
            $table->decimal('erogato_lavorazione', 15, 2)->default(0)->comment('Valore delle pratiche di finanziamento in lavorazione');

            // Provvigioni e Premi (Competenza)
            $table->decimal('provv_clientela', 15, 2)->default(0)->comment('TOTALE PROVVIGIONI RICONOSCIUTE DALLA CLIENTELA');
            $table->decimal('provv_istituto_comp', 15, 2)->default(0)->comment('TOTALE PROVVIGIONI RICONOSCIUTE DALL ISTITUTO EROGANTE');
            $table->decimal('premi_istituto_comp', 15, 2)->default(0)->comment('TOTALE PREMI RICONOSCIUTI DALL ISTITUTO EROGANTE');

            // (PAY-IN) Provvigioni Assicurative Maturate
            $table->decimal('payin_ass_banche', 15, 2)->default(0)->comment('(PAY-IN) PROVV. ASSICURATIVE - da banche/intermediari');
            $table->decimal('payin_ass_broker', 15, 2)->default(0)->comment('(PAY-IN) PROVV. ASSICURATIVE - da Broker');
            $table->decimal('payin_ass_broker_cap', 15, 2)->default(0)->comment('(PAY-IN) PROVV. ASSICURATIVE - da Broker Captive');

            // (PAY-OUT) Provvigioni Riconosciute alla Rete
            $table->decimal('payout_rete_credito', 15, 2)->default(0)->comment('AMMONTARE PROVVIGIONI RETE - INTERMEDIAZIONE CREDITO');
            $table->decimal('payout_rete_ass_banche', 15, 2)->default(0)->comment('(PAY-OUT) PROVV. RETE ASSICURATIVA - da banche');
            $table->decimal('payout_rete_ass_broker', 15, 2)->default(0)->comment('(PAY-OUT) PROVV. RETE ASSICURATIVA - da Broker');
            $table->decimal('payout_rete_ass_broker_cap', 15, 2)->default(0)->comment('(PAY-OUT) PROVV. RETE ASSICURATIVA - da Broker Captive');

            // Rivalse Art. 125
            $table->integer('num_rivalse')->default(0)->comment('N° RIVALSE AI SENSI DELL ART. 125 - SEXIES, DEL TUB');
            $table->decimal('importo_retrocesse', 15, 2)->default(0)->comment('AMMONTARE DELLE PROVVIGIONI RETROCESSE AL FINANZIATORE');

            // Dati anagrafici specifici della pratica
            $table->string('pratica')->nullable()->comment('Codice pratica');
            $table->string('istituto')->nullable()->comment('Mandataria fatturante');
            $table->string('agente')->nullable()->comment('Agente');
            $table->string('cliente')->nullable()->comment('Cliente');
            $table->string('tipo_prodotto')->nullable()->comment('Tipo finanziamento');
            $table->string('principal_type')->nullable()->comment('Tipo istituto broker / captive');

            // Date di stato della pratica
            $table->dateTime('sended_at')->nullable()->comment('Data invio');
            $table->dateTime('approved_at')->nullable()->comment('Data delibera');
            $table->dateTime('erogated_at')->nullable()->comment('Data erogazione');
            $table->dateTime('rejected_at')->nullable()->comment('Data respinto');
            $table->dateTime('storned_at')->nullable()->comment('Data storno');

            // Timestamps di sistema
            $table->timestamps();
            $table->softDeletes()->comment('Data di eliminazione logica del record');

            // Vincoli di chiave esterna
            $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oam_pratiches');
    }
};
