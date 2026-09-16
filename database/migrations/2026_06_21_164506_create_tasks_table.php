<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->comment('Definizione dei task automatici (es. OnBoarding, Renew, Audit) da applicare ai modelli');

            $table->id()->comment('ID univoco del task');
            $table->string('name')->unique()->comment('Nome univoco del task');  // OnBoarding, Renew, Audit
            $table->text('description')->nullable()->comment('Descrizione del task');
            $table->string('taskable')->nullable()->comment('Nome del modello a cui si applica il task');  // Modello
            $table->string('trigger_field')->nullable()->comment('Campo del modello da controllare');
            $table->string('trigger_state')->nullable()->comment('filled, empty, equals');
            $table->string('trigger_value')->nullable()->comment('Il valore specifico da controllare');
            $table->string('exclude_field')->nullable()->comment('Campo del modello da escludere se valorizzato');
            $table->string('exclude_state')->nullable()->comment('filled, empty, equals');
            $table->string('exclude_value')->nullable()->comment('Il valore specifico da controllare');

            $table->boolean('is_active')->default(true)->comment('Indica se il task è attualmente attivo');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
