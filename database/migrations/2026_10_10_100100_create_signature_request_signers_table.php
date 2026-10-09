<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('signature_request_signers')) {
            return;
        }

        Schema::create('signature_request_signers', function (Blueprint $table) {
            $table->comment('Firmatari di una richiesta di firma.');
            $table->id();
            $table->foreignId('signature_request_id')->constrained('signature_requests')->cascadeOnDelete();
            $table->unsignedTinyInteger('position');
            $table->string('slot', 30);
            $table->string('role', 20);
            $table->string('name');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('tax_code', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('signer_type', 20)->nullable()->comment('client | agent | user | manual');
            $table->string('signer_ref', 64)->nullable();
            $table->string('status', 20)->default('waiting');
            $table->dateTime('signed_at')->nullable();
            $table->string('provider_signer_ref')->nullable();
            $table->timestamps();
            $table->unique(['signature_request_id', 'slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signature_request_signers');
    }
};
