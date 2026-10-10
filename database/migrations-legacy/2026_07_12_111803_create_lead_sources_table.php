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
        Schema::create('lead_sources', function (Blueprint $blueprint) {
            // ID primario (BigInt Autoincrement) compatibile con la FK 'leadsource_id' della tabella 'clients'
            $blueprint->id();

            // Il nome visualizzato (es. "Call Center Outbound - Partner X", "Form Sito Web Principale")
            $blueprint->string('name')->comment('Nome identificativo della sorgente lead');

            // Macro Categoria per reportistica (Call Center, Web, Vecchio Cliente, ecc.)
            $blueprint->enum('type', ['call_center', 'website', 'old_client', 'social_media', 'referral', 'other'])
                ->default('other')
                ->comment('Macro-tipologia di canale per raggruppamento e statistiche ROI');

            // Dettagli tecnici o descrittivi (es. "Fornitore: LeadSpA - Campagna Maggio", "Campagna Google Ads CQS")
            $blueprint->text('description')->nullable()->comment('Note o dettagli specifici sulla sorgente');

            // Campi specifici per tracciabilità web (opzionali per UTM)
            $blueprint->string('utm_source')->nullable()->comment('Parametro UTM Source per lead da campagne web');
            $blueprint->string('utm_campaign')->nullable()->comment('Parametro UTM Campaign per lead da campagne web');

            // Stato di attivazione (permette di spegnere sorgenti obsolete senza rompere lo storico dei lead passati)
            $blueprint->boolean('is_active')->default(true)->comment('Indica se la sorgente è attualmente attiva e selezionabile');

            // Tracciabilità standard Laravel
            $blueprint->timestamps();

            // Indice sulle colonne di ricerca frequenti per ottimizzare i report sulle performance dei canali
            $blueprint->index(['type', 'is_active']);

            $blueprint->comment('Sorgenti/canali di acquisizione dei lead, usate per tracciabilità e statistiche ROI');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_sources');
    }
};
