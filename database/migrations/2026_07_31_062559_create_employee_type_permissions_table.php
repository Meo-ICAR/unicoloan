<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_type_permissions', function (Blueprint $table) {
            $table->id();

            // Usiamo integer per combaciare con l'INT della tabella employee_types
            $table->integer('employee_type_id');

            $table->string('resource');
            $table->string('action')->default('viewAny');

            $table->timestamps();

            // Foreign Key
            $table->foreign('employee_type_id')
                ->references('id')
                ->on('employee_types')
                ->cascadeOnDelete();

            // passiamo 'emp_type_perm_unique' come 2° argomento per forzare un nome breve all'indice
            $table->unique(['employee_type_id', 'resource', 'action'], 'emp_type_perm_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_type_permissions');
    }
};
