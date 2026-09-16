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
        Schema::table('oam_semestrales', function (Blueprint $table) {
            $table->string('abi_name', 255)->nullable()->after('prodotto_creditizio')->comment('Denominazione della banca (ABI) associata alla pratica.'); // Sostituisci 'abi' con il campo precedente se diverso
            $table->boolean('is_convenzione')->default(true)->comment('Convenzione SI/NO');
            $table->string('submission_type', 255)->nullable()->after('abi_name')->comment('Modalità di invio/trasmissione della pratica.');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('oam_semestrales', function (Blueprint $table) {
            //
        });
    }
};
