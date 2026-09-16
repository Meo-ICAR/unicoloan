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
        Schema::table('email_templates', function (Blueprint $table) {
            // Aggiunge l'identificativo per il multi-tenant/multi-app
            $table->string('app_identifier', 50)->nullable()
                ->after('is_active')->index()
                ->comment('Identificativo dell\'applicazione/tenant proprietaria del template email');
            //
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_templates', function (Blueprint $table) {
            //
        });
    }
};
