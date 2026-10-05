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
        Schema::create('training_records', function (Blueprint $table) {
            $table->comment('Registro delle attività formative erogate a dipendenti/collaboratori, con relativi esiti e certificazioni');

            $table->id()->comment('ID univoco del record formativo');

            // Chiave esterna verso l'azienda (UUID/Char 36)
            $table->char('company_id', 36)->comment('ID Azienda (Tenant) a cui appartiene il record formativo');

            // Relazione polimorfica (Genera trainable_type, trainable_id e l'indice composto)
            $table->uuidMorphs('trainable');

            // Enum Ambiti Regolatori
            $table->enum('regulatory_framework', [
                'gdpr', 'oam', 'ivass', 'sicurezza_lavoro', 'antiriciclaggio', 'mifid', 'other',
            ])->nullable()->comment('Ambito normativo di riferimento della formazione');

            $table->string('name')->nullable()->comment('Nome del corso/attività formativa');
            $table->text('description')->nullable()->comment('Descrizione del corso/attività formativa');
            $table->string('provider')->nullable()->comment('Ente/società che ha erogato la formazione');
            $table->string('trainer')->nullable()->comment('Nome del formatore/docente');

            // Enum Modalità di Erogazione
            $table->enum('delivery_mode', [
                'in_person', 'online', 'blended', 'on_the_job', 'webinar',
            ])->default('in_person')->comment('Modalità di erogazione della formazione');

            $table->date('training_date')->nullable()->comment('Data di svolgimento della formazione');
            $table->date('expiry_date')->nullable()->comment('Data di scadenza della validità della formazione/certificazione');
            $table->decimal('hours', 5, 1)->default(0.0)->comment('Numero di ore di formazione erogate');

            // Enum Esito
            $table->enum('outcome', [
                'passed', 'failed', 'attended', 'partial',
            ])->default('attended')->comment('Esito della formazione');

            $table->decimal('score', 5, 2)->nullable()->comment('Punteggio ottenuto nella valutazione finale');
            $table->boolean('certificate_issued')->default(false)->comment('Indica se è stato rilasciato un attestato/certificato');
            $table->string('certificate_number')->nullable()->comment('Numero identificativo dell\'attestato/certificato rilasciato');
            $table->text('notes')->nullable()->comment('Note aggiuntive sul record formativo');

            $table->timestamps();
            $table->softDeletes()->comment('Data di eliminazione logica del record');  // Abilita il deleted_at

            // Vincolo di integrità per la Company
            $table
                ->foreign('company_id')
                ->references('id')
                ->on('companies')  // Assicurati che la tabella delle aziende si chiami così
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('training_records');
    }
};
