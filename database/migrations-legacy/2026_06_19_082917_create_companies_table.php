<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            // Commento generale della tabella (Supportato da Laravel 9+)
            $table->comment('Tenant principale: Società di mediazione, Call Center, Sw House. Contiene dati legali e configurazioni globali.');

            // Chiave primaria UUID
            $table->uuid('id')->primary()->comment('UUID v4 generato da Laravel');

            // Dati Generali
            $table->string('name')->comment("Ragione Sociale dell'azienda");
            $table->string('vat_number', 50)->nullable()->comment('Partita IVA');
            $table->string('vat_name', 50)->nullable()->comment('Intestazione Partita IVA');

            // Dati OAM
            $table->string('oam', 50)->nullable()->comment('Numero di iscrizione OAM (Organismo Agenti e Mediatori)');
            $table->date('oam_at')->nullable()->comment("Data di iscrizione all'albo OAM");
            $table->string('oam_name')->nullable()->comment("Denominazione utilizzata per l'iscrizione OAM");

            // Dati RUI e IVASS
            $table->string('numero_iscrizione_rui')->nullable()->comment('Numero di iscrizione al RUI (Registro Unico Intermediari)');
            $table->string('ivass', 30)->nullable()->comment('Codice o numero di iscrizione IVASS');
            $table->date('ivass_at')->nullable()->comment("Data di iscrizione all'IVASS");
            $table->string('ivass_name')->nullable()->comment("Denominazione utilizzata per l'iscrizione IVASS");
            $table->enum('ivass_section', ['A', 'B', 'C', 'D', 'E'])->nullable()->comment('Sezione di appartenenza del registro IVASS');

            // Relazioni e Tipologie
            $table->string('sponsor')->nullable()->comment('Azienda o ente sponsor associato');
            $table->enum('company_type', ['mediatore', 'call center', 'hotel', 'sw house'])->nullable()->comment('Tipologia di azienda (classificazione interna)');

            // Personalizzazioni UI
            $table->text('page_header')->nullable()->comment("Codice HTML per l'intestazione personalizzata delle pagine/documenti");
            $table->text('page_footer')->nullable()->comment('Codice HTML per il pié di pagina personalizzato delle pagine/documenti');

            // Timestamps automatici (created_at, updated_at)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
