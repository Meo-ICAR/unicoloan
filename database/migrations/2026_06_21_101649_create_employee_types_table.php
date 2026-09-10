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
            // int firmato per restare compatibile con employee_type_permissions.employee_type_id
            $table->integer('id')->autoIncrement();
            $table->string('name')->nullable();
            $table->string('icon')->nullable();
            $table->string('companytype')->nullable();
            $table->boolean('is_external')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_types');
    }
};
