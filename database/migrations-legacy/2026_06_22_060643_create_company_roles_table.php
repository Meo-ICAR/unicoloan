<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('company_roles', function (Blueprint $table) {
            $table->id();

            // company_id come CHAR(36) / UUID
            $table->uuid('company_id')->comment('ID di collegamento alla tabella companies (formato UUID/Char)');

            $table->string('name')->nullable()->comment("Nome o descrizione descrittiva dell'ispezione");
            $table->enum('funzione', ['internal audit', 'compliance', 'aml', 'altro'])->nullable()->comment('Funzione aziendale');
            $table->boolean('is_external')->default(0)->comment('Figura esterna');
            $table->date('dal')->nullable()->comment('Data di inizio del periodo di ispezione');
            $table->date('al')->nullable()->comment('Data di fine del periodo di ispezione');

            // Enum con stringa vuota come default, seguendo il tuo SQL
            $table->enum('execution_method', ['documentale', '?', 'onsite'])->nullable()->comment('Metodo di esecuzione: tramite documenti, non specificato o sul posto');

            $table->string('expertName')->nullable()->comment("Nome e cognome dell'incaricato che esegue il controllo");
            $table->integer('n')->nullable()->comment('Numero identificativo interno o contatore progressivo');

            // Laravel crea automaticamente created_at e updated_at
            $table->timestamps();

            // Configurazione della Foreign Key (mantenendo il nome specifico della tua chiave)
            $table
                ->foreign('company_id', 'company_ispections_company_id_foreign')
                ->references('id')
                ->on('companies')
                ->onDelete('cascade');  // Consiglio: aggiungi il cascade o restrict in base alle tue necessità
        });

        // Aggiunge il commento alla tabella (Supportato a partire dalle ultime versioni di Laravel)
        DB::statement("ALTER TABLE `company_roles` comment 'Funzioni aziendali e Audit previsti nel periodo'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_roles');
    }
};
