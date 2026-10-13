<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Immobile oggetto del mutuo. `pratiches` e' una tabella di unico-core: stessa connessione delle sue migration.
 */
return new class extends Migration
{
    public function getConnection()
    {
        return config('unico-core.connection');
    }

    public function up(): void
    {
        Schema::table('pratiches', function (Blueprint $table) {
            $table->text('descrizione_immobile')->nullable()->after('net')->comment('Descrizione dell\'immobile oggetto del mutuo.');
        });
    }

    public function down(): void
    {
        Schema::table('pratiches', function (Blueprint $table) {
            $table->dropColumn('descrizione_immobile');
        });
    }
};
