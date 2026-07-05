<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logs_actividad', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('usuario_nombre', 150);
            $table->string('accion', 20);
            $table->string('entidad_tipo', 20)->nullable();
            $table->unsignedBigInteger('entidad_id')->nullable();
            $table->string('descripcion', 255);
            $table->dateTime('ocurrido_at');
            $table->timestamps();

            $table->index(['tenant_id', 'ocurrido_at']);
            $table->index(['tenant_id', 'entidad_tipo']);
            $table->index(['tenant_id', 'accion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logs_actividad');
    }
};
