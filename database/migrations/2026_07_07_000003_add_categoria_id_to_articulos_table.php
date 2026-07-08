<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articulos', function (Blueprint $table) {
            $table->foreignId('categoria_id')
                ->nullable()
                ->after('unidad')
                ->constrained('categorias_articulo')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('articulos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('categoria_id');
        });
    }
};
