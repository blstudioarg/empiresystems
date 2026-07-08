<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('nombre', 150);
            $table->string('empresa', 150)->nullable();
            $table->string('email')->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('estado', 15)->default('nuevo');
            $table->string('origen', 15)->default('manual');
            $table->foreignId('asignado_a')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('convertido_a_cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->string('motivo_descarte')->nullable();
            $table->text('notas')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['tenant_id', 'estado']);
            $table->index(['tenant_id', 'asignado_a']);
            $table->index(['tenant_id', 'email']);
            $table->index(['tenant_id', 'telefono']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
