<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('login_logo_path')->nullable()->after('logo_mini_path');
            $table->string('login_imagen_path')->nullable()->after('login_logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['login_logo_path', 'login_imagen_path']);
        });
    }
};
