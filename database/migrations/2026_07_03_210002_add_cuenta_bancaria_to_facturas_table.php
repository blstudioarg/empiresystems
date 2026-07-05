<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->foreignId('cuenta_bancaria_id')->nullable()->after('forma_pago')->constrained('cuentas_bancarias')->nullOnDelete();
            $table->string('cuenta_bancaria_banco')->nullable()->after('cuenta_bancaria_id');
            $table->string('cuenta_bancaria_iban', 34)->nullable()->after('cuenta_bancaria_banco');
            $table->string('cuenta_bancaria_titular')->nullable()->after('cuenta_bancaria_iban');
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropForeign(['cuenta_bancaria_id']);
            $table->dropColumn([
                'cuenta_bancaria_id',
                'cuenta_bancaria_banco',
                'cuenta_bancaria_iban',
                'cuenta_bancaria_titular',
            ]);
        });
    }
};
