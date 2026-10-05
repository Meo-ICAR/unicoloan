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
        Schema::create('organizations', function (Blueprint $table) {
            // Commento sulla tabella (MySQL Table Comment)
            $table->comment("Organismi di vigilanza e controllo a cui è sottoposto il mediatore creditizio (es. OAM, Banca d'Italia).");

            // Campi con relativi commenti (MySQL Column Comments)
            $table->id()->comment('Identificativo univoco del record');

            $table
                ->string('acronym', 50)
                ->unique()
                ->comment("Acronimo o sigla identificativa dell'ente (es. OAM, IVASS, UIF)");

            $table
                ->string('name')
                ->comment("Nome esteso e completo dell'organismo di vigilanza");

            $table
                ->text('description')
                ->nullable()
                ->comment("Dettagli aggiuntivi sui poteri, competenze o ambito di vigilanza dell'ente");

            $table
                ->string('reference_law')
                ->nullable()
                ->comment('Riferimento normativo istitutivo o principale (es. D.Lgs. 141/2010, TUB)');

            $table
                ->string('website')
                ->nullable()
                ->comment("Indirizzo del sito web ufficiale dell'ente");

            $table
                ->string('pec_email')
                ->nullable()
                ->comment("Indirizzo PEC ufficiale dell'ente per l'invio di comunicazioni formali di compliance");

            $table
                ->boolean('is_active')
                ->default(true)
                ->comment('Flag di stato: 1 = Attivo e vigilante, 0 = Storico/Disattivato');

            $table->timestamps();  // Genera automaticamente created_at e updated_at

            $table
                ->softDeletes()
                ->comment('Timestamp di cancellazione logica (Soft Delete) per preservare lo storico degli audit di compliance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
