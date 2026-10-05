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
        Schema::create('branches', function (Blueprint $table) {
            $table->comment('Filiali o sedi collegate alle aziende o polimorficamente ad altri modelli (Hotel, Call Center, ecc.)');

            $table->id()->comment('ID autoincrementale univoco della filiale');

            // Chiave esterna verso Companies (UUID)
            $table
                ->uuid('company_id')
                ->default('45d36df8-369f-40ce-b4fd-b5907c342fe9')
                ->comment('ID della company principale (FK su companies)');

            $table->string('name')->comment('Nome della filiale (es. Sede Milano, Ufficio Roma)');

            // Campi Indirizzo (posizionati in sequenza naturale)
            $table->string('address', 255)->nullable()->comment('Via/Piazza della sede');
            $table->string('street_number', 20)->nullable()->comment('Numero civico della sede');
            $table->string('city', 100)->nullable()->comment('Città della sede');
            $table->string('zip_code', 10)->nullable()->comment('CAP della sede');
            $table->string('province', 100)->nullable()->comment('Provincia della sede (nome esteso)');
            $table->string('region', 100)->nullable()->comment('Regione della sede');

            // Usiamo nullableUuidMorphs: una filiale e' sempre legata a una Company via
            // company_id, mentre l'aggancio polimorfico branchable e' opzionale.
            $table->nullableUuidMorphs('branchable');

            $table->boolean('is_main_office')->default(false)->comment('Indica se è la Sede Legale/Operativa principale (1 = Sì, 0 = No)');
            $table->boolean('is_active')->default(true)->comment('Indica se la filiale è attiva (1 = Sì, 0 = No)');

            // Dati del Manager
            $table->string('manager_first_name', 100)->nullable()->comment('Nome del responsabile della filiale');
            $table->string('manager_last_name', 100)->nullable()->comment('Cognome del responsabile della filiale');
            $table->string('manager_tax_code', 16)->nullable()->comment('Codice Fiscale del responsabile della filiale');

            // Date specifiche della filiale
            $table->date('founded_at')->nullable()->comment('Data apertura della filiale');
            $table->date('dismissed_at')->nullable()->comment('Data chiusura della filiale');

            // Timestamps e SoftDeletes di Laravel
            $table->timestamps();
            $table->softDeletes()->comment('Data di Referenza per eliminazione logica');

            // Indici e Vincoli
            $table->index(['branchable_type', 'branchable_id'], 'branches_branchable_index');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
