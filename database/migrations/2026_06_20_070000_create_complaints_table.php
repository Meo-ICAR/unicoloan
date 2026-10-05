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
        Schema::create('complaints', function (Blueprint $table) {
            $table->comment('Registro delle segnalazioni e reclami ricevuti');
            $table->id()->comment('ID univoco del reclamo/segnalazione');
            $table->uuid('company_id')->comment('Logical FK: db_bpm.companies');
            $table->morphs('complaintable');  // _type + _id
            $table->date('received_at')->comment('Data di ricezione della segnalazione');
            $table->string('subject')->comment('Oggetto sintetico del reclamo');
            $table->text('description')->comment('Descrizione dettagliata del reclamo');
            $table->string('status')->comment('Enum: ricevuto, in_lavorazione, accolto, respinto');
            $table->date('resolved_at')->nullable()->comment('Data di chiusura/risoluzione del reclamo');
            $table->timestamps();
            $table->softDeletes()->comment('Data di eliminazione logica del record');

            $table->index('status');
            $table->index('received_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql_compliance')->dropIfExists('complaints');
    }
};
