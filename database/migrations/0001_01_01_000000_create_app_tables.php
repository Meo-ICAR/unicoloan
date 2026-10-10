<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabelle proprie di unicoloan. Tutte le altre (utenti, aziende, documenti, audit, siti web...) sono del pacchetto
 * meo-icar/unico-core, caricate da AppServiceProvider: si creano con il normale `php artisan migrate`.
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
    }

    public function down(): void
    {
        foreach (['api_calls', 'failed_jobs', 'job_batches', 'jobs', 'cache_locks', 'cache', 'sessions', 'password_reset_tokens'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
