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
        Schema::create('jobs', function (Blueprint $table) {
            $table->comment('Coda dei job in attesa di elaborazione');
            $table->id()->comment('Identificativo univoco del job');
            $table->string('queue')->index()->comment('Nome della coda a cui appartiene il job');
            $table->longText('payload')->comment('Dati serializzati del job da eseguire');
            $table->unsignedSmallInteger('attempts')->comment('Numero di tentativi di esecuzione effettuati');
            $table->unsignedInteger('reserved_at')->nullable()->comment('Timestamp di presa in carico del job da un worker');
            $table->unsignedInteger('available_at')->comment('Timestamp da cui il job diventa disponibile per l\'esecuzione');
            $table->unsignedInteger('created_at')->comment('Timestamp di creazione del job');
        });

        Schema::create('job_batches', function (Blueprint $table) {
            $table->comment('Batch di job eseguiti in gruppo');
            $table->string('id')->primary()->comment('Identificativo univoco del batch');
            $table->string('name')->comment('Nome descrittivo del batch');
            $table->integer('total_jobs')->comment('Numero totale di job nel batch');
            $table->integer('pending_jobs')->comment('Numero di job ancora in attesa nel batch');
            $table->integer('failed_jobs')->comment('Numero di job falliti nel batch');
            $table->longText('failed_job_ids')->comment('Elenco serializzato degli identificativi dei job falliti');
            $table->mediumText('options')->nullable()->comment('Opzioni serializzate configurate per il batch');
            $table->integer('cancelled_at')->nullable()->comment('Timestamp di annullamento del batch');
            $table->integer('created_at')->comment('Timestamp di creazione del batch');
            $table->integer('finished_at')->nullable()->comment('Timestamp di completamento del batch');
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->comment('Registro dei job falliti durante l\'esecuzione');
            $table->id()->comment('Identificativo univoco del record');
            $table->string('uuid')->unique()->comment('UUID univoco del job fallito');
            $table->string('connection')->comment('Connessione della coda utilizzata');
            $table->string('queue')->comment('Nome della coda del job fallito');
            $table->longText('payload')->comment('Dati serializzati del job fallito');
            $table->longText('exception')->comment('Traccia dell\'eccezione che ha causato il fallimento');
            $table->timestamp('failed_at')->useCurrent()->comment('Data e ora del fallimento');

            $table->index(['connection', 'queue', 'failed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('failed_jobs');
    }
};
