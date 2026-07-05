<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campana_destinatarios', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('campana_id')->constrained('campanas')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('email')->nullable();
            $table->enum('estado', ['pendiente', 'enviado', 'fallido'])->default('pendiente');
            $table->string('error', 500)->nullable();
            $table->dateTime('enviado_at')->nullable();
            $table->timestamps();

            $table->unique(['campana_id', 'cliente_id']);
            $table->index(['campana_id', 'estado']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campana_destinatarios');
    }
};
