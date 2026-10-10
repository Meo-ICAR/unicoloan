<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dati societari richiesti dai moduli (QAV persona giuridica, fascicolo Corporate).
     * `clients` vive sulla connessione `mysql_proforma`; migration idempotente.
     */
    public function up(): void
    {
        $schema = Schema::connection('mysql_proforma');

        if (! $schema->hasTable('clients')) {
            return;
        }

        $schema->table('clients', function (Blueprint $table) use ($schema) {
            if (! $schema->hasColumn('clients', 'pec')) {
                $table->string('pec', 255)->nullable()->comment('Indirizzo PEC (posta elettronica certificata) della societa');
            }

            if (! $schema->hasColumn('clients', 'ateco_code')) {
                $table->string('ateco_code', 10)->nullable()->comment('Codice Ateco dell\'attivita prevalente della societa');
            }

            if (! $schema->hasColumn('clients', 'cciaa_registration')) {
                $table->string('cciaa_registration', 60)->nullable()->comment('Iscrizione alla Camera di Commercio (CCIAA / numero REA)');
            }
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('mysql_proforma');

        foreach (['cciaa_registration', 'ateco_code', 'pec'] as $column) {
            if ($schema->hasColumn('clients', $column)) {
                $schema->table('clients', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
