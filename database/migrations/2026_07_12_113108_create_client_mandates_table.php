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
        // `clients` vive sulla connessione `mysql_proforma` (tabella `proforma.clients`,
        // vedi App\Models\Client), non sulla connessione di default di questa migration.
        // Diventa un no-op dove `clients` non esiste, invece di fallire sul vincolo FK.
        if (Schema::hasTable('client_mandates') || ! Schema::connection('mysql_proforma')->hasTable('clients')) {
            return;
        }

        Schema::create('client_mandates', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID univoco mandato cliente');

            // Nessun vincolo FK: `clients` vive su una connessione/database diversa
            // (mysql_proforma), quindi non e' referenziabile con una FK nativa.
            $blueprint->unsignedBigInteger('client_id')
                ->comment('Riferimento al cliente coinvolto (mysql_proforma.clients.id)');
            $blueprint->index('client_id');

            $blueprint->string('numero_mandato')->unique()->comment('Numero identificativo mandato');
            $blueprint->date('data_firma_mandato')->comment('Innesca Instaurazione Rapporto AUI');
            $blueprint->date('data_scadenza_mandato')->comment('Innesca Chiusura Rapporto AUI (se non erogato prima)');
            $blueprint->decimal('importo_richiesto_mandato', 15, 2)->nullable()->comment('Importo massimo richiesto nel mandato');
            $blueprint->string('scopo_finanziamento')->nullable()->comment('Scopo del finanziamento (es. Acquisto Prima Casa, Liquidità)');
            $blueprint->date('data_consegna_trasparenza')->nullable()->comment('Deve essere <= data_firma');

            $blueprint->enum('stato', ['attivo', 'concluso_con_successo', 'scaduto', 'revocato'])
                ->default('attivo')
                ->comment('Stato del mandato');

            $blueprint->string('ruolo')->nullable()->comment('Ruolo del soggetto nella pratica (es. richiedente, garante, cointestatario)');
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

            $blueprint->comment('Mandati conferiti dai clienti finali, con dettagli su ruolo, importo richiesto e stato della pratica');
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
