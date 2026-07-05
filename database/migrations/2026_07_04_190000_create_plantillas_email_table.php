<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plantillas_email', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('titulo', 150);
            $table->string('asunto');
            $table->longText('cuerpo');
            $table->boolean('activa')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['tenant_id', 'activa']);
            $table->index(['tenant_id', 'titulo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plantillas_email');
    }
};
