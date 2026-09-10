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
        Schema::create('client_relations', function (Blueprint $table) {
            $table->id()->comment('ID univoco relazione cliente');

            // company_id è un CHAR(36), probabilmente un UUID
            $table->char('company_id', 36)->comment('ID società persona giuridica');

            // client_id e client_type_id sono INT UNSIGNED nel tuo SQL
            $table->unsignedInteger('client_id')->comment('ID persona fisica cliente');
            $table->unsignedInteger('client_type_id')->nullable()->comment('Tipo di cliente');

            $table->decimal('shares_percentage', 5, 2)->nullable()->comment('Percentuale quote possedute');
            $table->boolean('is_titolare')->default(false)->comment('Se è titolare/socio di maggioranza');

            $table->date('data_inizio_ruolo')->nullable()->comment('Data inizio ruolo');
            $table->date('data_fine_ruolo')->nullable()->comment('Data fine ruolo');

            $table->timestamps();

            // Foreign Keys con vincolo ON DELETE CASCADE come richiesto
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $table->foreign('client_type_id')->references('id')->on('client_types')->onDelete('cascade');

            // Commento della tabella
            $table->comment('Compositions societaria');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_relations');
    }
};
