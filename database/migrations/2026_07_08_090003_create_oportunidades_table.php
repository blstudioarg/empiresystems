<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oportunidades', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('titulo', 150);
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->string('etapa', 15)->default('nueva');
            $table->decimal('importe_estimado', 12, 2)->nullable();
            $table->foreignId('asignado_a')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo_perdida')->nullable();
            $table->dateTime('cerrada_at')->nullable();
            $table->text('notas')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['tenant_id', 'etapa']);
            $table->index(['tenant_id', 'lead_id']);
            $table->index(['tenant_id', 'cliente_id']);
            $table->index(['tenant_id', 'asignado_a']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oportunidades');
    }
};
