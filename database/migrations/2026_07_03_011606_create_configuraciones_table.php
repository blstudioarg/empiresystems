<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('configuraciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('clave', 100);
            $table->text('valor')->nullable();
            $table->string('tipo', 20);
            $table->string('grupo', 50);
            $table->string('descripcion')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'clave']);
            $table->index(['tenant_id', 'grupo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('configuraciones');
    }
};
