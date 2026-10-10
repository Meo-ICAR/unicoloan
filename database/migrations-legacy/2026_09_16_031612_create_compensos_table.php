<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Anagrafica degli stati di compenso/provvigione (App\Models\Compenso), usata
     * da `provvigioni.status_compenso` (App\Models\PROFORMA\Provvigione). Vive
     * sulla connessione mysql_proforma insieme a `provvigioni` e `pratiches`.
     */
    public function up(): void
    {
        $schema = Schema::connection('mysql_proforma');

        if ($schema->hasTable('compensos')) {
            return;
        }

        $schema->create('compensos', function (Blueprint $table) {
            $table->comment('Anagrafica degli stati possibili di un compenso/provvigione.');

            $table->string('status_compenso')->primary()->comment('Stato del compenso (chiave primaria).');
            $table->integer('isperfezionato')->default(0)->comment('Flag: indica se lo stato corrisponde a una pratica perfezionata/erogata.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql_proforma')->dropIfExists('compensos');
    }
};
