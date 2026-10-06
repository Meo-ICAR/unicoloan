<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `clients` vive sulla connessione `mysql_proforma` (tabella `proforma.clients`,
     * vedi App\Models\Client). La migration e' idempotente: diventa un no-op dove
     * la tabella non esiste o le colonne sono gia' presenti.
     */
    public function up(): void
    {
        $schema = Schema::connection('mysql_proforma');

        if (! $schema->hasTable('clients')) {
            return;
        }

        if (! $schema->hasColumn('clients', 'iban')) {
            $schema->table('clients', function (Blueprint $table) {
                $table->string('iban', 34)->nullable()->after('phone')->comment('IBAN del cliente (in chiaro)');
            });
        }

        if (! $schema->hasColumn('clients', 'employer_id')) {
            $schema->table('clients', function (Blueprint $table) {
                $table->unsignedBigInteger('employer_id')->nullable()->after('iban')
                    ->comment('Datore di lavoro (self-reference su clients.id)');
                $table->foreign('employer_id')->references('id')->on('clients')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('mysql_proforma');

        if ($schema->hasColumn('clients', 'employer_id')) {
            $schema->table('clients', function (Blueprint $table) {
                $table->dropForeign(['employer_id']);
                $table->dropColumn('employer_id');
            });
        }

        if ($schema->hasColumn('clients', 'iban')) {
            $schema->table('clients', function (Blueprint $table) {
                $table->dropColumn('iban');
            });
        }
    }
};
