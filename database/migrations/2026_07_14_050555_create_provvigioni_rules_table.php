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
        Schema::create('provvigioni_rules', function (Blueprint $table) {
            $table->id()->comment('ID univoco della regola provvigionale.');

            // --- Gerarchia e Applicabilità ---
            $table->unsignedBigInteger('tipoprodotto_id')
                ->nullable()
                ->comment('Prodotto a cui si applica il vincolo. Se NULL, si applica a tutti i prodotti della banca (DB Esterno).');

            $table->unsignedBigInteger('tipoprodotto_sub_id')
                ->nullable()
                ->comment('Sottoprodotto specifico (es. CQS Pensionati). Se NULL, si applica a tutto il macro-prodotto.');
            // Usiamo uuid() per creare la colonna senza generare vincoli fisici
            $table->uuid('clienti_id')
                ->nullable()
                ->comment('L\'istituto di credito che impone il vincolo (DB Esterno).');

            $table->unsignedBigInteger('kind_id')
                ->nullable()
                ->comment('ID Ruolo/Livello dell\'agente (es. Senior, Junior). Se NULL, si applica a tutta la rete.');
            // Usiamo uuid() per creare la colonna senza generare vincoli fisici
            $table->uuid('fornitori_id')
                ->nullable()
                ->comment('Agente');

            $table->boolean('coordinamento')->default(false)->comment('Provv di coordinamento');
            $table->boolean('iscliente')->default(false)->comment('Provv di mediazione da cliente');
            // --- Tipo di Calcolo ---
            $table->string('tipo_provvigioni', 30)
                ->default('lordo')
                ->comment('Tipo di calcolo: lordo, erogato, netto, fixed (importo fisso)');

            // --- Valore Economico ---
            $table->decimal('value', 10, 4)
                ->default(0)
                ->nullable()

                ->comment('Valore della provvigione. Può essere una percentuale (es. 2.5000 per 2.5%) o un importo fisso in euro.');

            // --- Gestione Storicità ---
            $table->date('valid_from')
                ->nullable()
                ->comment('Inizio validità temporale di questa specifica tariffa.');

            $table->date('valid_to')
                ->nullable()
                ->comment('Fine validità temporale di questa specifica tariffa.');

            $table->text('notes')
                ->nullable()
                ->comment('Note interne o chiarimenti sulla specifica regola provvigionale.');

            $table->timestamps();

            // Indici

            $table->index(['valid_from', 'valid_to'], 'idx_commission_validity');

            // Commento della tabella stessa (Supportato nativamente da MySQL)
            $table->comment('Matrice delle regole provvigionali. Gestisce la gerarchia a cascata (Prodotto -> Sottoprodotto -> Banca).');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provvigioni_rules');
    }
};
