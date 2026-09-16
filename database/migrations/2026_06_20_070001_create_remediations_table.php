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
        Schema::create('remediations', function (Blueprint $table) {
            $table->comment('Registro dettaglio dei rimedi obbligatori per le anomalie rilevate');
            $table->id()->comment('ID univoco del rimedio');
            $table->enum('remediation_type', ['AML', 'Gestione Reclami', 'Monitoraggio Rete', 'Privacy', 'Trasparenza', 'Assetto Organizzativo'])->nullable()->comment('categorizzare il rimedio');
            $table->string('name')->comment('nome rimedio');
            $table->string('code')->nullable()->comment('codice rimedio');
            $table->text('description')->nullable()->comment('Descrizione dettagliata del rimedio');
            $table->integer('timeframe_hours')->nullable()->comment('Tempo massimo, in ore, entro cui applicare il rimedio');
            $table->string('timeframe_desc')->nullable()->comment('Descrizione testuale della tempistica prevista');
            $table->timestamps();
            $table->softDeletes()->comment('Data di eliminazione logica del record');

            $table->index('remediation_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('remediations');
    }
};
