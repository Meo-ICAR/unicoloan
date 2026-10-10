<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A chi si rivolge il prodotto (persona fisica / giuridica) e se richiede il mandato.
     * `tipoprodotto` vive sulla connessione `mysql_proforma`; migration idempotente.
     */
    public function up(): void
    {
        $schema = Schema::connection('mysql_proforma');

        if (! $schema->hasTable('tipoprodotto')) {
            return;
        }

        $schema->table('tipoprodotto', function (Blueprint $table) use ($schema) {
            if (! $schema->hasColumn('tipoprodotto', 'for_person')) {
                $table->boolean('for_person')->default(true)->comment('Prodotto per persona fisica');
            }

            if (! $schema->hasColumn('tipoprodotto', 'for_company')) {
                $table->boolean('for_company')->default(true)->comment('Prodotto per persona giuridica');
            }

            if (! $schema->hasColumn('tipoprodotto', 'requires_mandate')) {
                $table->boolean('requires_mandate')->default(true)->comment('Serve il mandato (non per es. utenze)');
            }
        });

        $schema->getConnection()->table('tipoprodotto')->where('name', 'like', 'Utenz%')->update(['requires_mandate' => false]);
    }

    public function down(): void
    {
        $schema = Schema::connection('mysql_proforma');

        foreach (['for_person', 'for_company', 'requires_mandate'] as $column) {
            if ($schema->hasColumn('tipoprodotto', $column)) {
                $schema->table('tipoprodotto', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
