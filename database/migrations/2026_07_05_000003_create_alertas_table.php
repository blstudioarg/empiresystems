<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alertas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('miembro_equipo_id')->constrained('miembros_equipo')->restrictOnDelete();
            $table->foreignId('fichaje_id')->constrained('fichajes')->restrictOnDelete();
            $table->string('tipo', 30);
            $table->unsignedInteger('distancia_metros');
            $table->string('estado', 15)->default('nueva');
            $table->foreignId('resuelta_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('resuelta_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alertas');
    }
};
