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
        Schema::table('document_types', function (Blueprint $table) {
            $table->string('trigger_field')->nullable()->comment('Campo del modello da controllare')
                ->after('is_practice');
            $table->string('trigger_state')->nullable()->comment('filled, empty, equals');
            $table->string('trigger_value')->nullable()->comment('Il valore specifico da controllare');
            $table->string('exclude_field')->nullable()->comment('Campo del modello da escludere se valorizzato');
            $table->string('exclude_state')->nullable()->comment('filled, empty, equals');
            $table->string('exclude_value')->nullable()->comment('Il valore specifico da controllare');
            $table->integer('expire_days_before')->nullable()->comment('Numero di giorni prima della scadenza per inviare la notifica');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_type', function (Blueprint $table) {
            //
        });
    }
};
