<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Regole di associazione requisito <-> tipo/sottotipo prodotto
     * (App\Models\RequisitoTipoFinanziamento). Mai creata da una migration:
     * la tabella esisteva gia' in ambiente ma non era riproducibile su
     * un'installazione pulita.
     *
     * `tipoprodotto_id`/`tipoprodotto_sub_id` non hanno vincolo FK: quelle
     * tabelle vivono sulla connessione mysql_proforma (DB Esterno).
     */
    public function up(): void
    {
        if (Schema::hasTable('requisito_tipo_finanziamento')) {
            return;
        }

        Schema::create('requisito_tipo_finanziamento', function (Blueprint $table) {
            $table->comment('Regole di associazione tra requisiti a catalogo e tipo/sottotipo di prodotto finanziario.');

            $table->id()->comment('ID univoco della regola di associazione');
            $table->unsignedBigInteger('tipoprodotto_id')->nullable()->comment('Prodotto a cui si applica il vincolo (DB Esterno). Se NULL, si applica a tutti i prodotti');
            $table->unsignedBigInteger('tipoprodotto_sub_id')->nullable()->comment('Sottoprodotto specifico (es. CQS Pensionati). Se NULL, si applica a tutto il macro-prodotto');
            $table->foreignId('pratica_requisito_id')->constrained('pratica_requisiti')->cascadeOnDelete()->comment('Requisito a catalogo associato al prodotto/sottoprodotto.');
            $table->boolean('obbligatorio')->default(true)->comment("Flag: true se il requisito è obbligatorio per l'avanzamento");
            $table->integer('ordine')->default(0)->comment('Ordinamento di visualizzazione del requisito nella checklist del prodotto');

            $table->unique(['tipoprodotto_id', 'tipoprodotto_sub_id', 'pratica_requisito_id'], 'req_tf_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requisito_tipo_finanziamento');
    }
};
