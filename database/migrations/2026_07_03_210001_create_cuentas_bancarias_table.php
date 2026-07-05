<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuentas_bancarias', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('banco_id')->constrained('bancos');
            $table->string('alias');
            $table->string('iban', 34);
            $table->string('titular');
            $table->boolean('activa')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'activa']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuentas_bancarias');
    }
};
