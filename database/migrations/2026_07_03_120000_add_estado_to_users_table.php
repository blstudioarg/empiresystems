<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('estado', 20)->default('pendiente')->after('activo');
            $table->foreignId('aprobado_por')->nullable()->after('estado')->constrained('users')->nullOnDelete();
            $table->timestamp('aprobado_en')->nullable()->after('aprobado_por');

            $table->index(['tenant_id', 'estado']);
        });

        DB::table('users')->update(['estado' => 'aprobado']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'estado']);
            $table->dropConstrainedForeignId('aprobado_por');
            $table->dropColumn(['estado', 'aprobado_en']);
        });
    }
};
