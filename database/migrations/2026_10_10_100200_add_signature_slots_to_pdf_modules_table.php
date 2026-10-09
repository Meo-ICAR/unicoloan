<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('pdf_modules', 'signature_slots')) {
            return;
        }

        Schema::table('pdf_modules', function (Blueprint $table) {
            $table->json('signature_slots')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('pdf_modules', 'signature_slots')) {
            return;
        }

        Schema::table('pdf_modules', function (Blueprint $table) {
            $table->dropColumn('signature_slots');
        });
    }
};
