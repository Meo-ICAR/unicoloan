<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `clients` vive sulla connessione `mysql_proforma` (vedi App\Models\Client).
     * Idempotente: no-op dove la tabella non esiste o le colonne sono gia' presenti.
     */
    public function up(): void
    {
        $schema = Schema::connection('mysql_proforma');

        if (! $schema->hasTable('clients')) {
            return;
        }

        $schema->table('clients', function (Blueprint $table) use ($schema) {
            if (! $schema->hasColumn('clients', 'birth_date')) {
                $table->date('birth_date')->nullable()->comment('Data di nascita');
            }

            if (! $schema->hasColumn('clients', 'birth_place')) {
                $table->string('birth_place', 100)->nullable()->comment('Luogo di nascita (comune e provincia)');
            }

            if (! $schema->hasColumn('clients', 'sex')) {
                $table->char('sex', 1)->nullable()->comment('M | F');
            }

            if (! $schema->hasColumn('clients', 'citizenship')) {
                $table->string('citizenship', 60)->nullable()->comment('Cittadinanza');
            }
        });

        if (! $schema->hasColumn('clients', 'legal_representative_id')) {
            $schema->table('clients', function (Blueprint $table) {
                $table->unsignedBigInteger('legal_representative_id')->nullable()
                    ->comment('Legale rappresentante / amministratore (self-reference su clients.id)');
                $table->foreign('legal_representative_id')->references('id')->on('clients')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('mysql_proforma');

        if ($schema->hasColumn('clients', 'legal_representative_id')) {
            $schema->table('clients', function (Blueprint $table) {
                $table->dropForeign(['legal_representative_id']);
                $table->dropColumn('legal_representative_id');
            });
        }

        foreach (['citizenship', 'sex', 'birth_place', 'birth_date'] as $column) {
            if ($schema->hasColumn('clients', $column)) {
                $schema->table('clients', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
