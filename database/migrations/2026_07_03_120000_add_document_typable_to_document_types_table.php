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
            $table->boolean('is_versioned')->default(false)
                ->comment('Mantieni lo storico')->nullable();

            $table->string('document_typable')
                ->nullable()->comment('Alias del modello polimorfo a cui si applica il documento');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_types', function (Blueprint $table) {
            $table->dropColumn('document_typable');
            $table->dropColumn('is_versioned');
        });
    }
};
