<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id()->comment('ID univoco della voce di log');
            $table->string('log_name')->nullable()->index()->comment('Nome del canale/log a cui appartiene la voce (es. "default")');
            $table->text('description')->comment('Descrizione testuale dell\'attività registrata');
            $table->nullableMorphs('subject', 'subject');
            $table->string('event')->nullable()->comment('Tipo di evento registrato (created, updated, deleted, restored)');
            $table->nullableMorphs('causer', 'causer');
            $table->json('attribute_changes')->nullable()->comment('Attributi modificati, con valori precedenti e nuovi');
            $table->json('properties')->nullable()->comment('Dati aggiuntivi personalizzati associati all\'attività');
            $table->timestamps();

            $table->comment('Registro delle attività (audit log) generato da Spatie Activitylog per il tracciamento delle modifiche ai modelli');
        });
    }
};
