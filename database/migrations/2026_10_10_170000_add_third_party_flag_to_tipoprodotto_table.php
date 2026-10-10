<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prodotti che sono finanziamenti / impegni di terzi (trattenute in busta paga, altri finanziamenti del cliente):
     * non sono pratiche di lavorazione ne' richiedono il mandato.
     *
     * @var array<int, string>
     */
    private const THIRD_PARTY = ['CASSA MUTUA', 'Pignoramento', 'ASSICURAZIONE', 'Altro'];

    /**
     * Tipi aggiunti: voci tipiche di impegno del cliente, solo persone fisiche.
     *
     * @var array<int, string>
     */
    private const NEW_TYPES = ['Assegno di mantenimento', 'Sindacato', 'Fondo pensione', 'Carta revolving', 'Prestito INPS'];

    public function up(): void
    {
        $schema = Schema::connection('mysql_proforma');

        if (! $schema->hasTable('tipoprodotto')) {
            return;
        }

        if (! $schema->hasColumn('tipoprodotto', 'is_third_party')) {
            $schema->table('tipoprodotto', function (Blueprint $table) {
                $table->boolean('is_third_party')->default(false)->comment('Finanziamento / impegno di terzi (non e\' una pratica di lavorazione)');
            });
        }

        foreach (self::NEW_TYPES as $name) {
            if (! $schema->getConnection()->table('tipoprodotto')->where('name', $name)->exists()) {
                $schema->getConnection()->table('tipoprodotto')->insert(['name' => $name, 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        $schema->getConnection()->table('tipoprodotto')
            ->whereIn('name', [...self::THIRD_PARTY, ...self::NEW_TYPES])
            ->update(['is_third_party' => true, 'requires_mandate' => false]);

        $schema->getConnection()->table('tipoprodotto')
            ->whereIn('name', self::NEW_TYPES)
            ->update(['for_person' => true, 'for_company' => false]);
    }

    public function down(): void
    {
        $schema = Schema::connection('mysql_proforma');

        $schema->getConnection()->table('tipoprodotto')->whereIn('name', self::NEW_TYPES)->delete();

        if ($schema->hasColumn('tipoprodotto', 'is_third_party')) {
            $schema->table('tipoprodotto', fn (Blueprint $table) => $table->dropColumn('is_third_party'));
        }
    }
};
