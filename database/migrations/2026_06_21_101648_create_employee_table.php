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
        Schema::create('employees', function (Blueprint $table) {
            $table->comment('Anagrafica dipendenti e collaboratori commerciali con ruoli privacy, abilitazioni OAM/IVASS e gerarchie');

            $table->id()->comment('ID univoco interno del dipendente');

            // Relazioni Esterne
            $table->uuid('company_id')->nullable()->comment('ID Tenant proprietario (FK su companies)');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete()->comment('ID account utente collegato (FK su users)');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete()->comment('Sede');

            // Dati Anagrafici e Contatto
            $table->string('name')->nullable()->comment('Nome e Cognome del dipendente');
            $table->boolean('is_active')->default(true)->comment('Attivo');
            $table->string('role_title', 100)->nullable()->comment('Qualifica o titolo professionale');
            $table->string('cf')->nullable()->comment('Codice Fiscale del dipendente');
            $table->string('email')->nullable()->comment('Email aziendale/operatore');
            $table->string('pec')->nullable()->comment('PEC personale o aziendale dedicata');
            $table->string('phone')->nullable()->comment('Recapito telefonico / interno aziendale');
            $table->string('department', 100)->nullable()->comment('Dipartimento o area di appartenenza');

            // Abilitazioni Professionali (Mercato Finanziario / Assicurativo)
            $table->string('oam', 100)->nullable()->comment('Numero Iscrizione OAM');
            $table->date('oam_at')->nullable()->comment('Data iscrizione o rinnovo OAM');
            $table->string('oam_name', 100)->nullable()->comment('Denominazione o sezione specifica OAM');
            $table->string('numero_iscrizione_rui', 50)->nullable()->comment('Numero RUI (Registro Unico Intermediari Assicurativi)');
            $table->date('oam_dismissed_at')->nullable()->comment('Data di cessazione o revoca iscrizione OAM');
            $table->string('ivass', 100)->nullable()->comment('Numero Iscrizione IVASS');

            // Date Gestione Rapporto
            $table->date('hiring_date')->nullable()->comment('Data di assunzione o inizio collaborazione');
            $table->date('termination_date')->nullable()->comment('Data di cessazione del rapporto lavorativo');

            // Gerarchia Interna (Self-Reference)
            $table->unsignedBigInteger('coordinated_by_id')->nullable()->comment('ID del responsabile/coordinatore diretto');

            // Tipologie e Configurazione
            $table->string('employee_types')->default('dipendente')->comment('Tipologia contrattuale');
            $table->string('supervisor_type')->default('no')->comment('Livello di supervisione');

            // GDPR & Registro dei Trattamenti
            $table->string('privacy_role')->nullable()->comment('Ruolo designato ai fini privacy');
            $table->text('purpose')->nullable()->comment('Finalità del trattamento dati affidato');
            $table->text('data_subjects')->nullable()->comment('Categorie di interessati gestiti');
            $table->text('data_categories')->nullable()->comment('Categorie di dati trattati');
            $table->string('retention_period')->nullable()->comment('Tempi di conservazione dei dati gestiti');
            $table->string('extra_eu_transfer')->nullable()->comment("Eventuale trasferimento dati fuori dall'UE");
            $table->text('security_measures')->nullable()->comment('Misure di sicurezza tecniche e organizzative');
            $table->string('privacy_data')->nullable()->comment('Note accessorie o riferimenti a nomine esterne');

            // Flags
            $table->boolean('is_structure')->default(false)->comment('Indica se è un utente di struttura/backoffice');
            $table->boolean('is_ghost')->default(false)->comment('Utenza tecnica di sistema');

            // Audit Trails (Utenti)
            $table->unsignedBigInteger('created_by')->nullable()->comment('Logical FK: users.id, utente che ha creato il record');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Logical FK: users.id, utente che ha aggiornato per ultimo il record');
            $table->unsignedBigInteger('deleted_by')->nullable()->comment('Logical FK: users.id, utente che ha eliminato il record');

            // Timestamps e SoftDeletes
            $table->timestamps();
            $table->softDeletes()->comment('Data di eliminazione logica del record');

            // Indici Espliciti Richiesti
            $table->index('company_id', 'employees_company_id_foreign');
            $table->index('coordinated_by_id', 'employees_coordinated_by_id_foreign');

            // Definizione fisica del vincolo di auto-relazione gerarchica
            $table->foreign('coordinated_by_id')->references('id')->on('employees')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
