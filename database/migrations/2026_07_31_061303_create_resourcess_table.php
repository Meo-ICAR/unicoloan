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
        if (Schema::hasTable('resources')) {
            return;
        }

        Schema::create('resources', function (Blueprint $table) {
            $table->comment('Risorse/funzionalità applicative disponibili, raggruppabili per app e piano.');

            $table->id()->comment('ID univoco della risorsa.');
            $table->string('app_name')->comment('Applicazione di appartenenza della risorsa (es. CRM, PORTALE_OAM, FINANCE).');                                // Es. "CRM", "PORTALE_OAM", "FINANCE"
            $table->string('key')->comment('Identificatore univoco della risorsa all\'interno dell\'app (es. employees).');                                     // Identificatore (es. "employees")
            $table->string('name')->comment('Nome leggibile della risorsa (es. Dipendenti).');                                    // Nome leggibile (es. "Dipendenti")
            $table->string('group')->nullable()->comment('Gruppo di menu a cui appartiene la risorsa (es. Anagrafiche).');                       // Gruppo menu (es. "Anagrafiche")
            $table->enum('min_plan', ['BASE', 'MEDIUM', 'FULL'])->default('BASE')->comment('Piano minimo richiesto per accedere alla risorsa.');
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
        Schema::dropIfExists('resources');
    }
};
