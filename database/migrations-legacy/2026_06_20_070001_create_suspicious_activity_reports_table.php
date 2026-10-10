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
        Schema::create('suspicious_activity_reports', function (Blueprint $table) {
            $table->comment('Registro dettaglio delle segnalazioni di attività sospette');
            $table->id()->comment('ID univoco della segnalazione');
            $table->uuid('company_id')->comment('Logical FK: db_bpm.companies');
            $table->uuid('client_id')->nullable()->comment('Logical FK: clients.id, cliente finale coinvolto nella segnalazione');

            // Polimorfica per il segnalatore (Agent o Employee)
            // -----------------------------------------------------------------
            // 1. SU CHI? (Polimorfismo con UUID)
            // -----------------------------------------------------------------
            // Genera auditable_type e auditable_id. Perfetto per il componente MorphToSelect di Filament.
            $table->uuidMorphs('reportable');
            $table->timestamp('reported_at')->nullable()->comment('Data e ora in cui è stata effettuata la segnalazione');

            $table->json('anomalies_codes')->nullable()->comment('Elenco dei codici delle anomalie riscontrate');
            $table->text('description')->comment('Descrizione dettagliata dell\'attività sospetta');
            $table->enum('status', ['pending', 'investigated', 'reported', 'archived'])->default('pending')->comment('Stato di avanzamento della segnalazione');

            $table->timestamps();
            $table->softDeletes()->comment('Data di eliminazione logica del record');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suspicious_activity_reports');
    }
};
