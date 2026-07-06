<?php

namespace App\Console\Commands;

use App\Enums\EstadoAlerta;
use App\Enums\TipoAlerta;
use App\Enums\VeredictoCumplimiento;
use App\Models\Alerta;
use App\Models\MiembroEquipo;
use App\Models\Tenant;
use App\Support\Cumplimiento\ServicioCumplimiento;
use Illuminate\Console\Command;

/**
 * Job diario (D5/R5): evalúa el día anterior de cada miembro activo y crea alertas de
 * `ausencia_jornada`/`retraso_jornada` idempotentes (dedup por tenant+miembro+tipo+
 * referencia_fecha), sin tocar las alertas de fichaje fuera de rango existentes (FR-019/FR-020).
 */
class EvaluarCumplimientoJornada extends Command
{
    protected $signature = 'jornada:evaluar-cumplimiento';

    protected $description = 'Evalúa el cumplimiento de jornada del día anterior de cada miembro y crea alertas de ausencia/retraso (idempotente)';

    public function handle(ServicioCumplimiento $servicioCumplimiento): int
    {
        $ayer = now()->subDay()->startOfDay();

        Tenant::query()->each(function (Tenant $tenant) use ($servicioCumplimiento, $ayer) {
            $creadas = 0;

            MiembroEquipo::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('activo', true)
                ->each(function (MiembroEquipo $miembro) use ($servicioCumplimiento, $ayer, &$creadas) {
                    $resultado = $servicioCumplimiento->evaluarDia($miembro, $ayer);

                    $tipo = match ($resultado->veredicto) {
                        VeredictoCumplimiento::Ausencia => TipoAlerta::AusenciaJornada,
                        VeredictoCumplimiento::Retraso => TipoAlerta::RetrasoJornada,
                        default => null,
                    };

                    if ($tipo === null) {
                        return;
                    }

                    $existe = Alerta::withoutGlobalScopes()
                        ->where('tenant_id', $miembro->tenant_id)
                        ->where('miembro_equipo_id', $miembro->id)
                        ->where('tipo', $tipo->value)
                        ->whereDate('referencia_fecha', $ayer)
                        ->exists();

                    if ($existe) {
                        return;
                    }

                    Alerta::create([
                        'tenant_id' => $miembro->tenant_id,
                        'miembro_equipo_id' => $miembro->id,
                        'fichaje_id' => null,
                        'tipo' => $tipo,
                        'distancia_metros' => null,
                        'referencia_fecha' => $ayer->toDateString(),
                        'estado' => EstadoAlerta::Nueva,
                    ]);

                    $creadas++;
                });

            if ($creadas > 0) {
                $this->info("Tenant {$tenant->id}: {$creadas} alertas de cumplimiento creadas para {$ayer->toDateString()}.");
            }
        });

        return self::SUCCESS;
    }
}
