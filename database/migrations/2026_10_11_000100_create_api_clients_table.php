<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_clients', function (Blueprint $table) {
            $table->comment('App esterne autorizzate a consegnare richieste a unicoloan tramite API (es. unicoagent).');
            $table->id();
            $table->string('name')->unique()->comment('Nome dell\'app');
            $table->string('token_hash', 64)->unique()->comment('SHA-256 del token Bearer: il token in chiaro si mostra una volta sola');
            $table->text('secret')->comment('Segreto per la firma HMAC delle richieste (cifrato)');
            $table->boolean('is_active')->default(true)->comment('Se falso le richieste vengono rifiutate');
            $table->timestamp('last_used_at')->nullable()->comment('Ultima richiesta autenticata');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_clients');
    }
};
