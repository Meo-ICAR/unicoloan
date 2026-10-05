<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabelle presenti in unicooam ma assenti in unicooam_races.
     */
    public function up(): void
    {
        if (! Schema::hasTable('modules')) {
            Schema::create('modules', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('name');
                $table->string('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('company_modules')) {
            Schema::create('company_modules', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
                $table->string('plan_type')->nullable();
                $table->date('trial_ends_at')->nullable();
                $table->decimal('one_time_cost', 10, 2)->nullable();
                $table->decimal('monthly_cost', 10, 2)->nullable();
                $table->string('billing_frequency')->nullable();
                $table->date('last_invoice_at')->nullable();
                $table->date('last_payment_at')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'module_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_modules');
        Schema::dropIfExists('modules');
    }
};
