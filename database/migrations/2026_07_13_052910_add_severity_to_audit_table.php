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
        Schema::table('audits', function (Blueprint $table) {
            $table->enum('severity', ['Alto', 'Medio', 'Basso'])->nullable()
                ->comment('Severita rilievo')->after('outcome');
            $table->json('rilievi_codes')->nullable()->comment('Codici rilievi');
            $table->string('collaboratore')->nullable()->comment('Tipologia collaboratore');
            //
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit', function (Blueprint $table) {
            //
        });
    }
};
