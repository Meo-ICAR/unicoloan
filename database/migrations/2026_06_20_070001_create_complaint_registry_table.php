<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaint_registry', function (Blueprint $table) {
            $table->comment('Registro ufficiale Reclami e Segnalazioni (OAM, IVASS, Privacy)');
            $table->id()->comment('ID univoco del reclamo/segnalazione');
            $table->uuid('company_id')->comment('Logical FK: db_bpm.companies');

            // 1. IDENTIFICAZIONE E RICEZIONE
            $table->string('protocol_number', 50)->unique()->comment('Protocollo interno univoco')->nullable();
            $table->date('received_at')->nullable()->comment('Data ufficiale di ricezione');
            $table->string('reception_channel', 50)->comment('Enum: pec, raccomandata, email, brevi_manu')->nullable();
            $table->string('receiving_email')->nullable()->comment('La casella aziendale che ha ricevuto la notifica');

            // 2. IL RECLAMANTE (CHI FA LA SEGNALAZIONE)
            // Usa UUID Morphs se i tuoi modelli (Clienti, Fornitori) usano UUID come chiavi primarie,
            // altrimenti usa $table->nullableMorphs('complainant');
            $table->nullableUuidMorphs('complainant');
            $table->string('complainant_name')->nullable()->comment('Nome se il reclamante non è censito a DB');
            $table->string('complainant_email')->nullable()->comment('Contatto del reclamante');

            // 3. CLASSIFICAZIONE DEL RECLAMO
            $table->string('macro_category', 30)->comment('Enum: financial, privacy, insurance, operational')->nullable();
            $table->string('category', 50)->comment('Enum: delay, behavior, fraud, gdpr_access, gdpr_erasure')->nullable();

            // 4. OGGETTO DELLA SEGNALAZIONE E SOGGETTI COINVOLTI
            // A cosa si riferisce il reclamo? (Es. una specifica Pratica, un Contratto)
            $table->nullableUuidMorphs('subject');

            // Soggetti aziendali/esterni coinvolti (Cruciale per i Mediatori Creditizi)
            $table->uuid('agent_id')->nullable()->comment('Collaboratore/Agente della rete coinvolto');
            $table->uuid('bank_id')->nullable()->comment('Banca Mandante / Ente Erogante coinvolto');

            $table->text('description')->comment('Testo principale del reclamo');
            $table->decimal('financial_impact', 10, 2)->default(0.0)->comment('Eventuale richiesta danni o rimborso in EUR');

            // 5. WORKFLOW, STATO E SCADENZE
            $table->string('status', 30)->default('open')->comment('Enum: open, investigating, accepted, rejected, escalated')->nullable();
            $table->date('deadline_at')->nullable()->comment('Scadenza legale (es. 60gg per bancari, 30gg per GDPR)');
            $table->boolean('is_extended')->default(false)->comment('Se i termini sono stati estesi legalmente');

            // 6. RISOLUZIONE ED ESCALATION
            $table->timestamp('resolved_at')->nullable()->comment('Data e ora di risoluzione del reclamo');
            $table->text('resolution_notes')->nullable()->comment("Esito dell'istruttoria e motivazioni");
            $table->string('escalated_to', 50)->nullable()->comment('Se rigettato, eventuale ricorso a: abf, oam, ivass, garante');

            $table->timestamps();
            $table->softDeletes()->comment('Data di eliminazione logica del record');

            // INDICI OTTIMIZZATI PER LE DASHBOARD DI COMPLIANCE
            $table->index(['company_id', 'status']);
            $table->index('deadline_at');
            $table->index(['macro_category', 'category']);
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_compliance')->dropIfExists('complaint_registry');
    }
};
