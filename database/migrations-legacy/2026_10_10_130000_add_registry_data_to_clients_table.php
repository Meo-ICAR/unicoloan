<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dati del registro imprese e dell'ultimo bilancio (Openapi). `clients` vive sulla connessione `mysql_proforma`; migration idempotente.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const COLUMNS = [
        'legal_form' => ['string:100', 'Forma giuridica (registro imprese)'],
        'sdi_code' => ['string:10', 'Codice destinatario SDI'],
        'activity_status' => ['string:30', 'Stato attivita (es. ATTIVA)'],
        'company_started_at' => ['date', 'Data inizio attivita'],
        'share_capital' => ['decimal', 'Capitale sociale'],
        'employees' => ['unsignedInteger', 'Numero dipendenti (ultimo dato disponibile)'],
        'turnover' => ['decimal', 'Fatturato ultimo bilancio'],
        'net_worth' => ['decimal', 'Patrimonio netto ultimo bilancio'],
        'balance_year' => ['unsignedSmallInteger', 'Anno ultimo bilancio'],
        'registry_updated_at' => ['timestamp', 'Ultimo aggiornamento dei dati dal registro imprese'],
    ];

    public function up(): void
    {
        $schema = Schema::connection('mysql_proforma');

        if (! $schema->hasTable('clients')) {
            return;
        }

        $schema->table('clients', function (Blueprint $table) use ($schema) {
            foreach (self::COLUMNS as $name => [$type, $comment]) {
                if ($schema->hasColumn('clients', $name)) {
                    continue;
                }

                $column = match (true) {
                    str_starts_with($type, 'string:') => $table->string($name, (int) substr($type, 7)),
                    $type === 'decimal' => $table->decimal($name, 16, 2),
                    default => $table->{$type}($name),
                };

                $column->nullable()->comment($comment);
            }
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('mysql_proforma');

        foreach (array_keys(self::COLUMNS) as $column) {
            if ($schema->hasColumn('clients', $column)) {
                $schema->table('clients', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
