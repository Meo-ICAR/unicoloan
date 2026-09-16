<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Transizioni di stato ammesse tra pratica_stati (App\Models\PraticaStato::
     * transizioniSuccessive/transizioniPrecedenti). Mai creata da una migration:
     * la tabella esisteva gia' in ambiente ma non era riproducibile su
     * un'installazione pulita.
     */
    public function up(): void
    {
        if (Schema::hasTable('pratica_stati_transizioni')) {
            return;
        }

        Schema::create('pratica_stati_transizioni', function (Blueprint $table) {
            $table->comment('Transizioni di stato ammesse tra gli stati del workflow di una pratica.');

            $table->id()->comment('ID univoco della transizione');
            $table->foreignId('stato_da_id')->constrained('pratica_stati')->cascadeOnDelete()->comment('Stato di partenza della transizione.');
            $table->foreignId('stato_a_id')->constrained('pratica_stati')->cascadeOnDelete()->comment('Stato di arrivo della transizione.');

            $table->unique(['stato_da_id', 'stato_a_id'], 'unique_stato_transizione');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pratica_stati_transizioni');
    }
};
