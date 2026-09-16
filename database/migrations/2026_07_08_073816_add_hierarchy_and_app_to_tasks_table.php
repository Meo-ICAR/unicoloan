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
        Schema::table('tasks', function (Blueprint $table) {
            // Aggiunge la gerarchia parent/child
            $table->foreignId('parent_id')->nullable()->after('id')->constrained('tasks')->cascadeOnDelete()
                ->comment('Task padre nella gerarchia (per sotto-task)');

            // Aggiunge l'identificativo per il multi-tenant/multi-app
            $table->string('app_identifier', 50)->nullable()->after('description')->index()
                ->comment('Identificativo dell\'applicazione/tenant proprietaria del task');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            //
        });
    }
};
