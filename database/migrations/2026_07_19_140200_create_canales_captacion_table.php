<?php

use App\Models\Tenant;
use App\Support\SembradorCanalesCaptacion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo por tenant del canal de captación de un lead (data-model.md §1, research D3). Siembra
 * el conjunto inicial por defecto directamente en la propia migración para los tenants ya
 * existentes; los tenants nuevos lo reciben del alta de tenant (`SuperAdmin\TenantController`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canales_captacion', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('nombre', 80);
            $table->boolean('activo')->default(true);
            $table->smallInteger('orden')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'nombre']);
            $table->index(['tenant_id', 'activo']);
        });

        $ahora = now();

        Tenant::query()->pluck('id')->each(function (int $tenantId) use ($ahora) {
            $filas = collect(SembradorCanalesCaptacion::NOMBRES)->map(fn (string $nombre, int $orden) => [
                'tenant_id' => $tenantId,
                'nombre' => $nombre,
                'activo' => true,
                'orden' => $orden,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ])->all();

            DB::table('canales_captacion')->insert($filas);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canales_captacion');
    }
};
