<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asignaciones_horario', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('miembro_equipo_id')->constrained('miembros_equipo')->cascadeOnDelete();
            $table->foreignId('horario_id')->constrained('horarios')->restrictOnDelete();
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'miembro_equipo_id', 'vigente_desde'], 'asignaciones_horario_tenant_miembro_vigente_idx');
            $table->index(['tenant_id', 'horario_id'], 'asignaciones_horario_tenant_horario_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asignaciones_horario');
    }
};
