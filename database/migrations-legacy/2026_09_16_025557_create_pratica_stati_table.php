<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stati del workflow di una pratica (App\Models\PraticaStato), usati da
     * PraticaForm/PraticaStatoResource. Mai creata da una migration: la tabella
     * esisteva gia' in ambiente ma non era riproducibile su un'installazione pulita.
     */
    public function up(): void
    {
        if (Schema::hasTable('pratica_stati')) {
            return;
        }

        Schema::create('pratica_stati', function (Blueprint $table) {
            $table->comment('Stati del workflow di una pratica di finanziamento.');

            $table->id()->comment('ID univoco dello stato.');
            $table->string('codice')->unique()->comment('Codice univoco identificativo dello stato.');
            $table->string('name')->comment('Nome descrittivo dello stato.');
            $table->integer('ordine')->default(0)->comment('Ordine di visualizzazione dello stato nel workflow.');
            $table->boolean('is_rejected')->default(false)->comment('Indica se lo stato rappresenta un esito di rifiuto.');
            $table->boolean('is_working')->default(true)->comment('Indica se lo stato rappresenta una pratica ancora in lavorazione.');
            $table->boolean('is_estingued')->default(false)->comment('Indica se lo stato rappresenta una pratica estinta.');
            $table->string('colore')->default('gray')->comment('Colore associato allo stato per la visualizzazione nell\'interfaccia.');
            $table->string('icona')->nullable()->comment('Icona associata allo stato per la visualizzazione nell\'interfaccia.');
            $table->timestamps();
            $table->softDeletes()->comment('Data di eliminazione logica dello stato (Soft Delete).');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pratica_stati');
    }
};
