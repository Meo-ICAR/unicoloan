<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $blueprint) {
            $blueprint->timestamp('last_sent_at')->nullable()->after('status')
                ->comment('Data e ora ultimo sollecito inviato');

            $blueprint->unsignedInteger('reminders_count')->default(0)->after('last_sent_at')
                ->comment('Numero totale di solleciti inviati');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $blueprint) {
            $blueprint->dropColumn(['last_sent_at', 'reminders_count']);
        });
    }
};
