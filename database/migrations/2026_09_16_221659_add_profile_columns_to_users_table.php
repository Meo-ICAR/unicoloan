<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aggiunge il profilo polimorfico (profile_type/profile_id) usato dal
     * motore RBAC condiviso con unicobpm (vedi App\Models\User::profile()
     * e app/helpers.php).
     */
    public function up(): void
    {
        if (Schema::hasColumn('users', 'profile_type')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->nullableMorphs('profile');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropMorphs('profile');
        });
    }
};
