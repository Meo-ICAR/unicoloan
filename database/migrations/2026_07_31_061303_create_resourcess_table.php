<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->string('app_name');                                // Es. "CRM", "PORTALE_OAM", "FINANCE"
            $table->string('key');                                     // Identificatore (es. "employees")
            $table->string('name');                                    // Nome leggibile (es. "Dipendenti")
            $table->string('group')->nullable();                       // Gruppo menu (es. "Anagrafiche")
            $table->enum('min_plan', ['BASE', 'MEDIUM', 'FULL'])->default('BASE');
            $table->timestamps();

            // Indice univoco per evitare duplicati della stessa risorsa nella stessa app
            $table->unique(['app_name', 'key'], 'res_app_key_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resourcess');
    }
};
