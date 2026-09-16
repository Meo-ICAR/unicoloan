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
        Schema::create('audits', function (Blueprint $table) {
            $table->comment('Registro dei controlli (audit) eseguiti sui collaboratori/impiegati, richiesti da enti interni o esterni.');
            $table->id()->comment('Identificativo univoco del record');

            // -----------------------------------------------------------------
            // MULTI-TENANCY (Nativo in Filament 5)
            // -----------------------------------------------------------------
            // Filament isolerà i record in base alla Company attiva. Indispensabile l'indice.
            $table->foreignUuid('company_id')->nullable()->index()->constrained('companies')->cascadeOnDelete()->comment('Azienda (tenant) proprietaria del record');
            $table->text('name')->nullable()->comment('Nome audit');
            // -----------------------------------------------------------------
            // 1. SU CHI? (Polimorfismo con UUID)
            // -----------------------------------------------------------------
            // Genera auditable_type e auditable_id. Perfetto per il componente MorphToSelect di Filament.
            $table->uuidMorphs('auditable');

            // -----------------------------------------------------------------
            // 2. DA CHI? (Auditor - Utente Interno o Nome Esterno)
            // -----------------------------------------------------------------

            $table
                ->string('auditor_name')
                ->nullable()
                ->comment("Nome dell'ispettore o dell'auditor esterno/interno");

            // -----------------------------------------------------------------
            // 3. RICHIESTO DA? (Organismo di Vigilanza o Richiedente Interno)
            // -----------------------------------------------------------------
            // Chiave esterna verso la tua tabella 'organizations'. Indicizzato per i filtri di Filament.
            $table
                ->foreignId('organization_id')
                ->nullable()
                ->index()
                ->constrained('organizations')
                ->nullOnDelete()
                ->comment("L'organismo di vigilanza che ha richiesto l'audit (es. OAM). Null se interno.");

            // -----------------------------------------------------------------
            // DETTAGLI, DATE E STATO (Ideali per Enum e DatePicker di Filament)
            // -----------------------------------------------------------------
            // Usiamo index() sulle date e sugli stati perché in Filament saranno colonne di punta per l'ordinamento e i filtri
            $table->date('scheduled_at')->nullable()->index()->comment('Data pianificata');
            $table->date('executed_at')->nullable()->index()->comment('Data di esecuzione effettiva');

            // In Filament 5 userai un PHP Backed Enum per lo stato (es. AuditStatus::class)
            $table->string('status')->default('planned')->index()->comment('Stato: planned, in_progress, completed, cancelled');

            $table->string('protocol_number')->nullable()->unique()->comment('Numero di protocollo ufficiale (es. OAM)');

            // Altri campi stringa gestibili con i nuovi ToggleButtons di Filament 5
            $table->string('origin_type')->default('internal')->index()->comment('internal o external_incoming');
            $table->string('execution_method')->default('documentale')->comment('Metodo: documentale, in_loco, intervista');

            // -----------------------------------------------------------------
            // ESITI E NOTE (Ideali per RichEditor / Textarea di Filament)
            // -----------------------------------------------------------------
            $table->text('scope')->nullable()->comment('Ambito oggettivo del controllo');
            $table->string('outcome')->nullable()->index()->comment('Esito: Passato, Con Rilievi, Fallito');  // Indicizzato per la reportistica
            $table->text('summary')->nullable()->comment('Sintesi dei risultati');
            $table->text('auditor_notes')->nullable()->comment("Note riservate dell'auditor");
            $table->text('remediation_plan')->nullable()->comment('Piano di rimedio richiesto');  // Cambiato a text se il piano è lungo
            $table->date('followup_date')->nullable()->comment('Data di verifica dei rimedi');

            $table->timestamps();
            $table->softDeletes()->comment('Data di eliminazione logica del record');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audits');
    }
};
