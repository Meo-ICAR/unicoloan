<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql';

    public function up(): void
    {
        if (Schema::connection('mysql')->hasColumn('documents', 'renewed_by_id')) {
            return;
        }

        Schema::connection('mysql')->table('documents', function (Blueprint $table) {
            $table->uuid('renewed_by_id')->nullable()->after('renewed_by')->index()->comment('Documento che sostituisce questo (es. versione firmata)');
        });
    }

    public function down(): void
    {
        if (! Schema::connection('mysql')->hasColumn('documents', 'renewed_by_id')) {
            return;
        }

        Schema::connection('mysql')->table('documents', function (Blueprint $table) {
            $table->dropColumn('renewed_by_id');
        });
    }
};
