<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabelle proprie di unicoloan. Tutte le altre (utenti, aziende, documenti, audit...) sono del pacchetto
 * meo-icar/unico-core e si creano con `php artisan unico-core:migrate`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->bigInteger('expiration')->index();
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->bigInteger('expiration')->index();
        });

        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });

        Schema::create('api_calls', function (Blueprint $table) {
            $table->comment('Registro delle chiamate a web service esterni (Cerved, sanctions.io...) con esito e costo.');
            $table->id();
            $table->string('provider', 30)->index();
            $table->string('operation', 60);
            $table->string('subject_type')->nullable();
            $table->string('subject_id', 36)->nullable()->comment('id intero o UUID del record interrogato');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('status', 20)->index();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->decimal('cost', 10, 4)->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->json('request')->nullable()->comment('parametri inviati, senza credenziali');
            $table->longText('response')->nullable()->comment('risposta del provider (JSON)');
            $table->string('summary')->nullable()->comment('esito in sintesi');
            $table->string('reference')->nullable()->comment('id della ricerca presso il provider');
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
        });

        // Siti web e landing page con il tracciamento della conformità legale. Il soggetto (`websiteable`) può essere
        // un'azienda del pacchetto (id intero) o un'anagrafica Proforma (UUID): per questo l'id è una stringa.
        Schema::create('websites', function (Blueprint $table) {
            $table->comment('Registro dei siti web e delle landing page gestite dal network con tracciamento conformità legale');

            $table->id()->comment('ID univoco del sito');
            $table->unsignedBigInteger('company_id')->nullable()->index()->comment('Company proprietaria (companies è del pacchetto: nessun vincolo, per non dipendere dall\'ordine delle migration)');
            $table->string('name')->comment('Nome del sito');
            $table->string('type')->nullable()->comment('Tipologia sito (es. vetrina, e-commerce, landing)');
            $table->unsignedInteger('clienti_id')->nullable()->comment('Mandante di riferimento / ID cliente esterno');
            $table->boolean('is_active')->default(true)->comment('Stato di attivazione del sito (1 = Attivo, 0 = Inattivo)');
            $table->string('domain')->comment('Dominio o sottodominio principale (es. www.races.it)');
            $table->boolean('is_typical')->default(true)->comment('Sito utilizzato per attività tipica aziendale (1 = Sì, 0 = No)');
            $table->date('privacy_date')->nullable()->comment('Data ultimo aggiornamento privacy policy');
            $table->date('transparency_date')->nullable()->comment('Data ultimo aggiornamento trasparenza');
            $table->date('privacy_prior_date')->nullable()->comment('Data precedente aggiornamento privacy policy');
            $table->date('transparency_prior_date')->nullable()->comment('Data precedente aggiornamento trasparenza');
            $table->string('url_privacy')->nullable()->comment('URL completo alla pagina privacy policy');
            $table->string('url_cookies')->nullable()->comment('URL completo alla pagina cookie policy');
            $table->boolean('is_footercompilant')->default(false)->comment('Indica se il footer contiene i dati legali ed è conforme GDPR (1 = Sì)');
            $table->string('url_transparency')->nullable()->comment('URL completo alla pagina di trasparenza / dati societari');
            $table->boolean('is_iso27001_certified')->default(false)->comment('Indica se il sito risiede su infrastruttura certificata ISO 27001');
            $table->string('websiteable_type')->comment('Tipo del soggetto proprietario (alias morph)');
            $table->string('websiteable_id', 36)->comment('ID del soggetto proprietario: intero (core) o UUID (Proforma)');
            $table->timestamps();
            $table->softDeletes()->comment('Data e ora di eliminazione logica');

            $table->index(['websiteable_type', 'websiteable_id']);
            $table->index('is_active', 'websites_is_active_index');
            $table->index('domain', 'websites_domain_index');
        });
    }

    public function down(): void
    {
        foreach (['api_calls', 'websites', 'failed_jobs', 'job_batches', 'jobs', 'cache_locks', 'cache', 'sessions', 'password_reset_tokens'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
