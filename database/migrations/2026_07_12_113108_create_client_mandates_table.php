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
        Schema::create('client_mandates', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID univoco mandato cliente');

            // Allineato a BigInteger per matchare perfettamente il DDL di 'clients'
            $blueprint->foreignId('client_id')
                ->comment('Riferimento al cliente coinvolto')
                ->constrained('clients')
                ->onDelete('cascade');

            $blueprint->string('numero_mandato')->unique()->comment('Numero identificativo mandato');
            $blueprint->date('data_firma_mandato')->comment('Innesca Instaurazione Rapporto AUI');
            $blueprint->date('data_scadenza_mandato')->comment('Innesca Chiusura Rapporto AUI (se non erogato prima)');
            $blueprint->decimal('importo_richiesto_mandato', 15, 2)->nullable()->comment('Importo massimo richiesto nel mandato');
            $blueprint->string('scopo_finanziamento')->nullable()->comment('Scopo del finanziamento (es. Acquisto Prima Casa, Liquidità)');
            $blueprint->date('data_consegna_trasparenza')->nullable()->comment('Deve essere <= data_firma');

            $blueprint->enum('stato', ['attivo', 'concluso_con_successo', 'scaduto', 'revocato'])
                ->default('attivo')
                ->comment('Stato del mandato');

            $blueprint->string('ruolo')->nullable();
            $blueprint->string('name')->nullable()->comment('Descrizione');
            $blueprint->text('notes')->nullable()->comment('Note specifiche sul ruolo per questa pratica (es. "Garante solo per quota 50%")');
            $blueprint->text('purpose_of_relationship')->nullable()->comment('Es: Acquisto prima casa');
            $blueprint->text('funds_origin')->nullable()->comment('Es: Risparmi, donazione, stipendio');

            $blueprint->boolean('oam_delivered')->default(false)->comment('Foglio informativo consegnato a questo soggetto?');

            $blueprint->enum('role_risk_level', ['basso', 'medio', 'alto'])
                ->nullable()
                ->comment('Livello rischio specifico ruolo nella pratica');

            // Supporto integrato SoftDeletes richiesto dal tuo DDL (deleted_at)
            $blueprint->softDeletes();
            $blueprint->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_mandates');
    }
};
