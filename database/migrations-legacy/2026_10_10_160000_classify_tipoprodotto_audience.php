<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prodotti rivolti solo a persone fisiche (lavoratori e pensionati): cessione del quinto, delega e TFS.
     * Verificato sui dati: nessuna pratica di questi prodotti ha una P.IVA come codice fiscale.
     *
     * @var array<int, string>
     */
    private const PERSON_ONLY = ['Cessione', 'Delega', 'ALTRA DELEGAZIONE IMPORTO CONTENUTO', 'TFS'];

    public function up(): void
    {
        $schema = Schema::connection('mysql_proforma');

        if (! $schema->hasColumn('tipoprodotto', 'for_company')) {
            return;
        }

        $schema->getConnection()->table('tipoprodotto')->whereIn('name', self::PERSON_ONLY)->update(['for_person' => true, 'for_company' => false]);
    }

    public function down(): void
    {
        $schema = Schema::connection('mysql_proforma');

        if ($schema->hasColumn('tipoprodotto', 'for_company')) {
            $schema->getConnection()->table('tipoprodotto')->whereIn('name', self::PERSON_ONLY)->update(['for_company' => true]);
        }
    }
};
