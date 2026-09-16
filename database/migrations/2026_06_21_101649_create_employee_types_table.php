<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Anagrafica dei tipi di dipendente / ruoli usata dal motore RBAC
     * (App\Models\EmployeeType e App\Models\EmployeeTypePermission).
     *
     * La tabella su alcuni ambienti e' stata creata fuori dalle migration:
     * la guardia hasTable evita errori quando esiste gia'.
     */
    public function up(): void
    {
        if (Schema::hasTable('employee_types')) {
            return;
        }

        Schema::create('employee_types', function (Blueprint $table) {
            $table->comment('Anagrafica dei tipi di dipendente/ruolo usati dal motore RBAC');

            // int firmato per restare compatibile con employee_type_permissions.employee_type_id
            $table->integer('id')->autoIncrement()->comment('ID univoco del tipo di dipendente');
            $table->string('name')->nullable()->comment('Nome del tipo di dipendente/ruolo');
            $table->string('icon')->nullable()->comment('Icona associata al tipo di dipendente');
            $table->string('companytype')->nullable()->comment('Tipologia di azienda a cui si applica il ruolo');
            $table->boolean('is_external')->default(false)->comment('Indica se il ruolo è riservato a collaboratori esterni');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_types');
    }
};
