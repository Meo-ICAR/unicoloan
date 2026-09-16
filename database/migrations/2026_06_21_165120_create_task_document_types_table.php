<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_document_types', function (Blueprint $table) {
            $table->comment('Associazione tra task e tipi di documento richiesti per completarli');

            $table->id()->comment('ID univoco dell\'associazione');

            // Chiave esterna verso i task
            $table->foreignId('task_id')->constrained()->onDelete('cascade')->comment('Task a cui è associato il tipo di documento');

            // Chiave esterna verso i tipi di documento
            // NOTA: Se la tabella 'document_types' usa UUID (char 36), usa: $table->foreignUuid('document_type_id')
            $table->foreignId('document_type_id')->constrained()->onDelete('cascade')->comment('Tipo di documento richiesto dal task');
            $table->string('slug')->nullable()->comment('Slug univoco per URL');
            // Un campo extra molto utile per la logica di business (Opzionale)
            $table->boolean('is_required')->default(true)->comment('Se il documento è obbligatorio per questo task');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_document_types');
    }
};
