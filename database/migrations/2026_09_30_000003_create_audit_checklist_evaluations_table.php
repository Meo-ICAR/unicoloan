<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('audit_checklist_evaluations')) {
            return;
        }

        Schema::create('audit_checklist_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained('audits')->cascadeOnDelete();
            $table->unsignedBigInteger('audit_checklist_item_id');
            $table->unsignedBigInteger('external_processor_id')->nullable();
            $table->text('internal_documentation')->nullable();
            $table->text('vendor_evidence_notes')->nullable();
            $table->string('gap_status')->default('da_verificare');
            $table->text('gap_notes')->nullable();
            $table->boolean('is_vendor_scope')->default(false);
            $table->boolean('is_optional')->default(false);
            $table->date('verified_at')->nullable();
            $table->date('next_review_at')->nullable();
            $table->string('verified_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['audit_id', 'audit_checklist_item_id'], 'audit_checklist_eval_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_checklist_evaluations');
    }
};
