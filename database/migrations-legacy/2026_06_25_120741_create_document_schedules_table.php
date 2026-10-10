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
        Schema::create('document_schedules', function (Blueprint $table) {
            $table->id()->comment('ID univoco della pianificazione documentale');
            // Chiave esterna sul documento reale
            $table->foreignUuid('document_id')->constrained()->cascadeOnDelete()->nullable()->comment('Riferimento al documento reale collegato');
            $table->uuidMorphs('documentable');
            $table->string('entity_name')->comment('Nome leggibile del Soggetto (es: "Mario Rossi")');  // Nome leggibile del Soggetto (es: "Mario Rossi")
            // Raggruppamento pulito per il codice (ex alias stringa)
            $table->string('documentable_group_key')->comment('Chiave di raggruppamento del soggetto polimorfo (es: "employee|uuid")');  // es: "employee|uuid"

            // Campi piatti salvati per Filament (Zero JOIN o relazioni polimorfiche a runtime)
            $table->string('document_name')->comment('Nome del documento, denormalizzato per le tabelle Filament');  // Nome del documento
            $table->string('document_type_name')->comment('Nome del tipo di documento, denormalizzato per le tabelle Filament');  // Nome del tipo di documento

            // Campi di controllo per scadenze e solleciti
            $table->date('expires_at')->nullable()->comment('Data di scadenza del documento');
            $table->integer('days_until_expiry')->comment('Numero di giorni mancanti alla scadenza');
            $table->string('status')->comment('Stato corrente della pianificazione (es. in scadenza, scaduto, valido)');
            $table->timestamp('last_sent_at')->nullable()->comment('Data ultimo sollecito inviato');
            $table->integer('reminders_count')->default(0)->comment('Numero totale di solleciti inviati');

            $table->timestamps();

            // Indici per velocizzare Filament
            $table->index(['expires_at', 'status']);
            $table->index('documentable_group_key');

            $table->comment('Pianificazione denormalizzata delle scadenze documentali usata da Filament per solleciti e reportistica');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_schedules');
    }
};
