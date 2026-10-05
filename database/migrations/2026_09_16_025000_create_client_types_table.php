<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Anagrafica dei tipi/ruoli privacy di cliente (App\Models\ClientType).
     * Colonne dedotte dal PHPDoc del modello: la tabella non era mai stata
     * creata da nessuna migration, quindi `client_relations.client_type_id`
     * non ha mai potuto risolvere una relazione reale.
     *
     * `id` e' un intero a 32 bit (increments) per combaciare con
     * `client_relations.client_type_id`, che e' unsignedInteger.
     */
    public function up(): void
    {
        if (Schema::hasTable('client_types')) {
            return;
        }

        Schema::create('client_types', function (Blueprint $table) {
            $table->comment('Anagrafica dei tipi/ruoli privacy di cliente.');

            $table->increments('id')->comment('ID univoco tipo cliente');
            $table->string('name')->comment('Descrizione');
            $table->boolean('is_person')->default(false)->comment('Persona fisica (true) o giuridica (false)');
            $table->boolean('is_company')->default(false)->comment('Indica se è una società/azienda');
            $table->string('privacy_role')->nullable()->comment('Ruolo Privacy (es. Titolare Autonomo, Responsabile Esterno)');
            $table->text('purpose')->nullable()->comment('Finalità del trattamento');
            $table->text('data_subjects')->nullable()->comment('Categorie di Interessati');
            $table->text('data_categories')->nullable()->comment('Categorie di Dati Trattati');
            $table->text('retention_period')->nullable()->comment('Tempi di Conservazione (Data Retention)');
            $table->text('extra_eu_transfer')->nullable()->comment('Trasferimento Extra-UE');
            $table->text('security_measures')->nullable()->comment('Misure di Sicurezza');
            $table->text('privacy_data')->nullable()->comment('Altri Dati Privacy');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_types');
    }
};
