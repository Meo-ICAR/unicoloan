<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->comment('Registro dei media caricati nel sistema');
            $table->id()->comment('Identificativo univoco del media');

            $table->morphs('model');
            $table->uuid()->nullable()->unique()->comment('UUID univoco pubblico del media');
            $table->string('collection_name')->comment('Nome della collezione di media a cui appartiene il file');
            $table->string('name')->comment('Nome descrittivo assegnato al media');
            $table->string('file_name')->comment('Nome del file fisico memorizzato su disco');
            $table->string('mime_type')->nullable()->comment('Tipo MIME del file');
            $table->string('disk')->comment('Disco di storage su cui è salvato il file originale');
            $table->string('conversions_disk')->nullable()->comment('Disco di storage su cui sono salvate le conversioni del file');
            $table->unsignedBigInteger('size')->comment('Dimensione del file in byte');
            $table->json('manipulations')->comment('Manipolazioni applicate al media, in formato JSON');
            $table->json('custom_properties')->comment('Proprietà personalizzate del media, in formato JSON');
            $table->json('generated_conversions')->comment('Elenco delle conversioni generate per il media, in formato JSON');
            $table->json('responsive_images')->comment('Dati delle immagini responsive generate, in formato JSON');
            $table->unsignedInteger('order_column')->nullable()->index()->comment('Ordine di visualizzazione del media all\'interno della collezione');

            $table->nullableTimestamps();
        });
    }
};
