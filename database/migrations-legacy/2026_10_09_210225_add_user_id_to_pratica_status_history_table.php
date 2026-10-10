<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('mysql_proforma')->hasColumn('pratica_status_history', 'user_id')) {
            return;
        }

        Schema::connection('mysql_proforma')->table('pratica_status_history', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('notes')->index()->comment('mysql.users.id: chi ha cambiato lo stato o inserito l\'annotazione');
        });
    }

    public function down(): void
    {
        if (! Schema::connection('mysql_proforma')->hasColumn('pratica_status_history', 'user_id')) {
            return;
        }

        Schema::connection('mysql_proforma')->table('pratica_status_history', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });
    }
};
