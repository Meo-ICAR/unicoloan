<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Soggetti collegati a una pratica non di cessione/delega: richiedente, coobbligato, garante.
     * `pratiches` e `clients` vivono sulla connessione `mysql_proforma`, quindi niente FK native.
     */
    public function up(): void
    {
        $schema = Schema::connection('mysql_proforma');

        if ($schema->hasTable('pratica_clients')) {
            return;
        }

        $schema->create('pratica_clients', function (Blueprint $table) {
            $table->comment('Richiedente, coobbligati e garanti di una pratica (clients) per i prodotti diversi da cessione/delega.');
            $table->id();
            $table->char('pratica_id', 36)->index()->comment('pratiches.id');
            $table->unsignedInteger('client_id')->index()->comment('clients.id');
            $table->string('role', 20)->comment('richiedente | coobbligato | garante');
            $table->timestamps();

            $table->unique(['pratica_id', 'client_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_proforma')->dropIfExists('pratica_clients');
    }
};
