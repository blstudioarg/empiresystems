<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('localidades', function (Blueprint $table) {
            $table->string('id', 5)->primary();
            $table->string('provincia_id', 2);
            $table->string('nombre');
            $table->timestamps();

            $table->foreign('provincia_id')->references('id')->on('provincias');
            $table->index(['provincia_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('localidades');
    }
};
