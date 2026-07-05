<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proveedores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('nombre')->nullable();
            $table->string('razon_social')->nullable();
            $table->string('nif', 15)->nullable();
            $table->string('direccion')->nullable();
            $table->string('cp', 10)->nullable();
            $table->string('ciudad')->nullable();
            $table->string('provincia')->nullable();
            $table->string('pais', 2)->default('ES');
            $table->string('email')->nullable();
            $table->string('telefono', 30)->nullable();
            $table->text('notas')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['tenant_id', 'nif']);
            $table->index(['tenant_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proveedores');
    }
};
