<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('signature_requests')) {
            return;
        }

        Schema::create('signature_requests', function (Blueprint $table) {
            $table->comment('Richieste di firma di un Document presso un provider (busta di firma).');
            $table->id();
            $table->char('document_id', 36)->index()->comment('documents.id');
            $table->string('provider', 30);
            $table->string('provider_ref')->nullable()->index();
            $table->string('status', 20)->default('pending')->index();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('signed_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('last_synced_at')->nullable();
            $table->dateTime('last_event_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->string('token', 64)->nullable()->unique()->comment('riservato alla firma a distanza via link (fase 2)');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signature_requests');
    }
};
