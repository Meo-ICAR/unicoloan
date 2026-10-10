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
        Schema::create('websites', function (Blueprint $table) {
            $table->comment('Registro dei siti web e delle landing page gestite dal network con tracciamento conformità legale');

            $table->id()->comment('ID univoco del sito');

            // Relazione principale (Nullable)
            $table->uuid('company_id')->nullable()->comment('ID della company proprietaria (FK su companies)');

            $table->string('name')->comment('Nome del sito');
            $table->string('type')->nullable()->comment('Tipologia sito (es. vetrina, e-commerce, landing)');
            $table->unsignedInteger('clienti_id')->nullable()->comment('Mandante di riferimento / ID cliente esterno');
            $table->boolean('is_active')->default(true)->comment('Stato di attivazione del sito (1 = Attivo, 0 = Inattivo)');
            $table->string('domain')->comment('Dominio o sottodominio principale (es. www.races.it)');
            $table->boolean('is_typical')->default(true)->comment('Sito utilizzato per attività tipica aziendale (1 = Sì, 0 = No)');

            // Date adempimenti legali
            $table->date('privacy_date')->nullable()->comment('Data ultimo aggiornamento privacy policy');
            $table->date('transparency_date')->nullable()->comment('Data ultimo aggiornamento trasparenza');
            $table->date('privacy_prior_date')->nullable()->comment('Data precedente aggiornamento privacy policy');
            $table->date('transparency_prior_date')->nullable()->comment('Data precedente aggiornamento trasparenza');

            // Link legali e Compliance
            $table->string('url_privacy')->nullable()->comment('URL completo alla pagina privacy policy');
            $table->string('url_cookies')->nullable()->comment('URL completo alla pagina cookie policy');
            $table->boolean('is_footercompilant')->default(false)->comment('Indica se il footer contiene i dati legali ed è conforme GDPR (1 = Sì)');
            $table->string('url_transparency')->nullable()->comment('URL completo alla pagina di trasparenza / dati societari');
            $table->boolean('is_iso27001_certified')->default(false)->comment('Indica se il sito risiede su infrastruttura certificata ISO 27001');

            // FIX: Usiamo uuidMorphs perché i soggetti (es. Company o Clienti) usano chiavi UUID
            $table->uuidMorphs('websiteable');

            // Timestamps e SoftDeletes
            $table->timestamps();
            $table->softDeletes()->comment('Data e ora di Referenza per eliminazione logica');

            // Indici espliciti richiesti
            $table->index('is_active', 'websites_is_active_index');
            $table->index('domain', 'websites_domain_index');

            // Vincolo Chiave Esterna
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('websites');
    }
};
