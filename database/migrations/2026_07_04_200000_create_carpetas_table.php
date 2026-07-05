<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carpetas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('parent_id')->nullable()->constrained('carpetas')->nullOnDelete();
            $table->string('nombre');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['tenant_id', 'parent_id']);
            $table->unique(['tenant_id', 'parent_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carpetas');
    }
};
