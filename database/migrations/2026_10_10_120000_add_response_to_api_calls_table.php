<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('api_calls', 'response')) {
            Schema::table('api_calls', function (Blueprint $table) {
                $table->longText('response')->nullable()->after('request')->comment('risposta del provider (JSON)');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('api_calls', 'response')) {
            Schema::table('api_calls', function (Blueprint $table) {
                $table->dropColumn('response');
            });
        }
    }
};
