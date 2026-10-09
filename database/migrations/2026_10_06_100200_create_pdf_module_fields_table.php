<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pdf_module_fields')) {
            return;
        }

        Schema::create('pdf_module_fields', function (Blueprint $table) {
            $table->comment('Mappatura campo AcroForm -> chiave dati (ModuleSourceKey) per ogni modulo PDF.');

            $table->id();
            $table->foreignId('pdf_module_id')->constrained('pdf_modules')->cascadeOnDelete();
            $table->string('pdf_field_name');
            $table->string('pdf_field_type', 20)->default('text')->comment('text | checkbox');
            $table->string('source_key')->nullable()->comment('Valore di ModuleSourceKey; null = campo non compilato');
            $table->string('formatter', 30)->nullable()->comment('Valore di ModuleFormatter');
            $table->string('checkbox_on_value', 50)->nullable()->comment('Valore dello stato "spuntato" (es. si, Yes)');
            $table->string('checkbox_when')->nullable()->comment('Chiave ModuleSourceKey, con ! iniziale per negare');
            $table->timestamps();

            $table->unique(['pdf_module_id', 'pdf_field_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdf_module_fields');
    }
};
