<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('miembros_equipo', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('puesto', 120)->nullable();
            $table->string('trabajo_direccion')->nullable();
            // Nullable: un miembro puede no tener ubicación de trabajo configurada todavía
            // (edge case del spec) — sus fichajes se marcan SinUbicacion, sin alerta de distancia.
            $table->decimal('trabajo_latitud', 10, 7)->nullable();
            $table->decimal('trabajo_longitud', 10, 7)->nullable();
            $table->unsignedInteger('distancia_max_metros');
            $table->string('casa_direccion')->nullable();
            $table->decimal('casa_latitud', 10, 7)->nullable();
            $table->decimal('casa_longitud', 10, 7)->nullable();
            $table->unsignedInteger('distancia_casa_trabajo_metros')->nullable();
            $table->boolean('activo')->default(true);
            $table->dateTime('dado_baja_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('miembros_equipo');
    }
};
