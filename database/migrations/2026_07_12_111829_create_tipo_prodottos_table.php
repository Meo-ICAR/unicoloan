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
        Schema::create('tipo_prodottos', function (Blueprint $table) {
            $table->id()->comment('ID univoco del tipo di prodotto finanziario');
            $table->timestamps();

            $table->comment('Tipologie di prodotto finanziario offerte tramite le pratiche di finanziamento');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipo_prodottos');
    }
};
