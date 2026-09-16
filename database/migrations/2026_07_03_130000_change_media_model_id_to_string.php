<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE media MODIFY model_id VARCHAR(255) NOT NULL COMMENT 'ID del modello collegato al media, memorizzato come stringa per supportare chiavi non numeriche (es. UUID)'");

            return;
        }

        Schema::table('media', function (Blueprint $table) {
            $table->string('model_id')->comment('ID del modello collegato al media, memorizzato come stringa per supportare chiavi non numeriche (es. UUID)')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE media MODIFY model_id BIGINT UNSIGNED NOT NULL');

            return;
        }

        Schema::table('media', function (Blueprint $table) {
            $table->unsignedBigInteger('model_id')->change();
        });
    }
};
