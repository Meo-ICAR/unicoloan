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
        Schema::create('email_templates', function (Blueprint $table) {
            $table->comment('Registro dei template email configurabili');
            $table->id()->comment('Identificativo univoco del template');
            $table->string('code')->unique()->comment('Codice univoco di task  (es. AUDIT_REMOTE)');
            $table->string('name')->comment('Nome descrittivo per gli utenti');
            $table->string('subject')->comment('Oggetto della mail');
            $table->longText('body')->comment('Corpo della mail (supporta HTML e placeholders)');
            $table->json('placeholders')->nullable()->comment('Elenco delle variabili utilizzabili (es. {agente_nome})');
            $table->boolean('is_active')->default(true)->comment('Stato del template');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
