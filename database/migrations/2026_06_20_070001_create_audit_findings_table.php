<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_findings', function (Blueprint $table) {
            $table->comment('Registro dei rilievi (anomalie/non conformità) emersi durante gli audit');
            $table->id()->comment('ID univoco del rilievo');

            // Relazioni principali
            $table->foreignId('audit_id')->constrained('audits')->cascadeOnDelete()->comment('Audit a cui si riferisce il rilievo');

            // Ottima la denormalizzazione del company_id per il multi-tenant (evita JOIN pesanti)
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete()->comment('Azienda (tenant) a cui appartiene il rilievo');

            // Anomalia rilevata
            $table->string('title')->comment('Titolo sintetico del rilievo');
            $table->text('description')->comment('Descrizione dettagliata dell\'anomalia rilevata');

            // Ottimizzazione: Passiamo a stringhe per usare i PHP Enums. Aggiungiamo gli indici per query veloci.
            $table->string('severity')->default('minor')->index()->comment('Gravità del rilievo (es. minor, major, critical)');
            $table->string('status')->default('open')->index()->comment('Stato di avanzamento del rilievo (es. open, closed)');

            // Approfondimento richiesto
            $table->boolean('requires_investigation')->default(false)->comment('Indica se il rilievo richiede un approfondimento investigativo');
            $table->text('investigation_notes')->nullable()->comment('Note relative all\'approfondimento investigativo');
            $table->date('investigation_deadline')->nullable()->comment('Scadenza entro cui completare l\'approfondimento');

            // Misura correttiva (Remediation)
            $table->boolean('requires_corrective_action')->default(true)->comment('Indica se il rilievo richiede una misura correttiva');
            $table->text('corrective_action_description')->nullable()->comment('Descrizione della misura correttiva da adottare');

            // Suggerito dal catalogo: aggiungiamo l'indice anche qui se prevedi di farci reportistica
            $table->unsignedBigInteger('remediation_id')->nullable()->index()->comment('Logical FK: remediations.id, rimedio associato al rilievo');
            $table->date('corrective_action_deadline')->nullable()->comment('Scadenza entro cui applicare la misura correttiva');

            // Chiusura e risoluzione
            $table->date('resolved_at')->nullable()->comment('Data di chiusura/risoluzione del rilievo');
            $table->text('resolution_notes')->nullable()->comment('Note sull\'esito della risoluzione');

            $table->timestamps();
            $table->softDeletes()->comment('Data di eliminazione logica del record');
        });
    }

    public function down(): void
    {
        // Ottimizzazione: rimozione del vincolo ->connection('mysql')
        Schema::dropIfExists('audit_findings');
    }
};
