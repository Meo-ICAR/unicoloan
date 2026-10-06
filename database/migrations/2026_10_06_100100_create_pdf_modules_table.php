<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pdf_modules')) {
            return;
        }

        Schema::create('pdf_modules', function (Blueprint $table) {
            $table->comment('Moduli PDF compilabili (AcroForm) presenti in storage/app/public/module.');

            $table->id();
            $table->string('name')->comment('Nome mostrato all\'operatore');
            $table->string('file_path')->unique()->comment('Percorso relativo al disco public');
            $table->string('version')->nullable()->comment('Versione del modulo (es. 03_2026)');
            $table->json('tipi_prodotto')->nullable()
                ->comment('Nomi tipo_prodotto pertinenti (come in pratiches.tipo_prodotto); null = tutti');
            $table->string('client_scope', 30)->default('entrambi')
                ->comment('persona_fisica | persona_giuridica | entrambi');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdf_modules');
    }
};
