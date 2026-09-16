<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Istanze operative dei requisiti su una specifica pratica
     * (App\Models\PraticaRequisitoOperativo). Mai creata da una migration:
     * la tabella esisteva gia' in ambiente ma non era riproducibile su
     * un'installazione pulita.
     *
     * `pratica_id` non ha vincolo FK: `pratiches` vive sulla connessione
     * mysql_proforma, quindi non e' referenziabile con una FK nativa qui.
     */
    public function up(): void
    {
        if (Schema::hasTable('pratica_requisiti_operativi')) {
            return;
        }

        Schema::create('pratica_requisiti_operativi', function (Blueprint $table) {
            $table->comment('Istanze operative dei requisiti applicati a una specifica pratica.');

            $table->id()->comment("ID univoco dell'istanza del requisito sulla pratica");
            $table->unsignedBigInteger('pratica_id')->comment('FK verso la pratica di riferimento (mysql_proforma.pratiches.id)');
            $table->foreignId('pratica_requisito_id')->constrained('pratica_requisiti')->restrictOnDelete()->comment('Requisito a catalogo di riferimento.');
            $table->string('stato')->default('da_richiedere')->comment('Stato del task: da_richiedere, richiesto, approvato, rifiutato, non_necessario');
            $table->timestamp('data_richiesta')->nullable()->comment('Data e ora in cui il requisito è stato richiesto al cliente/ente');
            $table->timestamp('data_completamento')->nullable()->comment('Data e ora di approvazione o evasione del requisito');
            $table->text('note')->nullable()->comment('Note operative o dettagli sulle problematiche del requisito');
            $table->timestamps();

            $table->index('pratica_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pratica_requisiti_operativi');
    }
};
