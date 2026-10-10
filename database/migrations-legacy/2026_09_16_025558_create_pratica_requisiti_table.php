<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catalogo dei requisiti/task paralleli di pratica (App\Models\PraticaRequisito).
     * Mai creata da una migration: la tabella esisteva gia' in ambiente ma non
     * era riproducibile su un'installazione pulita.
     */
    public function up(): void
    {
        if (Schema::hasTable('pratica_requisiti')) {
            return;
        }

        Schema::create('pratica_requisiti', function (Blueprint $table) {
            $table->comment('Catalogo dei requisiti/task paralleli associabili a una pratica.');

            $table->id()->comment('ID univoco del requisito a catalogo');
            $table->string('codice')->unique()->comment('Codice univoco del requisito (es. polizza_vita, certificato_stipendio)');
            $table->string('name')->comment('Nome descrittivo del requisito/task parallelo');
            $table->text('descrizione')->nullable()->comment("Descrizione o istruzioni operative per l'evasione del requisito");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pratica_requisiti');
    }
};
