<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('series', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('codigo', 10);
            $table->string('tipo');
            $table->unsignedSmallInteger('ejercicio')->nullable();
            $table->unsignedInteger('proximo_numero')->default(1);
            $table->string('formato');
            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->index('tenant_id');
            $table->unique(['tenant_id', 'codigo', 'ejercicio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('series');
    }
};
