<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campanas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('plantilla_email_id')->nullable()->constrained('plantillas_email')->nullOnDelete();
            $table->string('asunto');
            $table->longText('cuerpo');
            $table->enum('estado', ['borrador', 'en_curso', 'finalizada'])->default('borrador');
            $table->unsignedInteger('total_destinatarios')->default(0);
            $table->unsignedInteger('enviados')->default(0);
            $table->unsignedInteger('fallidos')->default(0);
            $table->dateTime('enviada_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campanas');
    }
};
