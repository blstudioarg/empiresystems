<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fichajes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('miembro_equipo_id')->constrained('miembros_equipo')->restrictOnDelete();
            $table->string('tipo', 15);
            $table->dateTime('ocurrido_at');
            $table->string('resultado_ubicacion', 15)->nullable();
            $table->unsignedInteger('distancia_metros')->nullable();
            $table->unsignedInteger('precision_metros')->nullable();
            $table->foreignId('corrige_fichaje_id')->nullable()->constrained('fichajes')->nullOnDelete();
            $table->string('motivo')->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_origen', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'miembro_equipo_id', 'ocurrido_at'], 'fichajes_tenant_miembro_fecha_idx');
            $table->index(['tenant_id', 'ocurrido_at'], 'fichajes_tenant_fecha_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fichajes');
    }
};
