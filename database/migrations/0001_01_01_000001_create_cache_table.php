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
        Schema::create('cache', function (Blueprint $table) {
            $table->comment('Voci della cache applicativa');
            $table->string('key')->primary()->comment('Chiave univoca della voce di cache');
            $table->mediumText('value')->comment('Valore serializzato memorizzato in cache');
            $table->bigInteger('expiration')->index()->comment('Timestamp di scadenza della voce di cache');
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->comment('Lock utilizzati per la sincronizzazione della cache');
            $table->string('key')->primary()->comment('Chiave del lock');
            $table->string('owner')->comment('Identificativo del proprietario del lock');
            $table->bigInteger('expiration')->index()->comment('Timestamp di scadenza del lock');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cache');
        Schema::dropIfExists('cache_locks');
    }
};
