<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('oam_codes', function (Blueprint $table) {
            $table->comment('Tabella di lookup globale (Senza Tenant): Ambiti operativi OAM.');

            $table->id()->comment('ID autoincrementante');
            $table->string('code')->nullable()->comment('Codice ambito OAM');
            $table->string('name')->nullable()->comment('Descrizione ambito operativo');
            $table->string('description')->nullable()->comment('Codice e Descrizione ambito operativo');
            $table->string('tipo_prodotto')->nullable()->comment('Tipo finanziamento');
            $table->boolean('is_dummy')->default(false)->comment('Flag per identificare record fittizio per associare prodotti non presenti in OAM');
            $table->boolean('is_active')->default(true)->comment('Flag per identificare record attivo');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oam_codes');
    }
};
