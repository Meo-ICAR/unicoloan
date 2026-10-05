<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_reminders', function (Blueprint $table) {
            $table->id()->comment('ID univoco del sollecito inviato');
            $table->uuid('document_id')->comment('Riferimento al documento per cui è stato inviato il sollecito');
            $table->unsignedSmallInteger('days_before')->comment('Giorni mancanti alla scadenza al momento dell\'invio');
            $table->string('recipient_email')->comment('Indirizzo email del destinatario del sollecito');
            $table->string('status', 20)->default('sent')->comment('Stato di invio del sollecito');
            $table->text('error_message')->nullable()->comment('Messaggio di errore in caso di invio fallito');
            $table->timestamp('sent_at')->comment('Data e ora di invio del sollecito');
            $table->timestamps();

            $table->foreign('document_id')->references('id')->on('documents')->cascadeOnDelete();
            $table->unique(['document_id', 'days_before'], 'document_reminders_document_days_unique');
            $table->index('sent_at');

            $table->comment('Storico dei solleciti email inviati per documenti in scadenza');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_reminders');
    }
};
