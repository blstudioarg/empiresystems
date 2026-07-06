<?php

namespace App\Services;

use App\Enums\EstadoAlerta;
use App\Enums\ResultadoUbicacionFichaje;
use App\Enums\TipoAlerta;
use App\Enums\TipoEventoFichaje;
use App\Exceptions\FichajeBloqueadoException;
use App\Models\Alerta;
use App\Models\Fichaje;
use App\Models\MiembroEquipo;
use App\Models\User;
use App\Support\ConfigFichajes;
use App\Support\Haversine;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Único punto de escritura del ledger `fichajes`: fija la hora de servidor (FR-002), calcula el
 * veredicto Haversine dentro/fuera contra la ubicación de trabajo del propio miembro (FR-011a) y
 * aplica las reglas de secuencia (FR-006). No expone update/delete: las correcciones son un
 * método aparte que crea un evento enlazado (ver `corregir()`, añadido en US3).
 */
class RegistroFichajes
{
    public function registrar(
        MiembroEquipo $miembro,
        TipoEventoFichaje $tipo,
        ?float $latitud = null,
        ?float $longitud = null,
        ?int $precision = null,
        ?string $ipOrigen = null,
        ?string $userAgent = null,
    ): Fichaje {
        return DB::transaction(function () use ($miembro, $tipo, $latitud, $longitud, $precision, $ipOrigen, $userAgent) {
            $this->validarSecuencia($miembro, $tipo);

            [$resultado, $distancia] = $this->evaluarUbicacion($miembro, $latitud, $longitud);

            if ($resultado === ResultadoUbicacionFichaje::Fuera
                && ConfigFichajes::geofencingBloqueante($miembro->tenant_id)) {
                throw new FichajeBloqueadoException(
                    'El fichaje está fuera del perímetro autorizado y el geofencing bloqueante está activo para este tenant.'
                );
            }

            $fichaje = Fichaje::create([
                'tenant_id' => $miembro->tenant_id,
                'miembro_equipo_id' => $miembro->id,
                'tipo' => $tipo,
                'ocurrido_at' => now(),
                'resultado_ubicacion' => $resultado,
                'distancia_metros' => $distancia,
                'precision_metros' => $precision,
                'ip_origen' => $ipOrigen,
                'user_agent' => $userAgent,
            ]);

            if ($resultado === ResultadoUbicacionFichaje::Fuera) {
                Alerta::create([
                    'tenant_id' => $miembro->tenant_id,
                    'miembro_equipo_id' => $miembro->id,
                    'fichaje_id' => $fichaje->id,
                    'tipo' => TipoAlerta::FichajeFueraDeRango,
                    'distancia_metros' => $distancia,
                    'estado' => EstadoAlerta::Nueva,
                ]);
            }

            return $fichaje;
        });
    }

    /**
     * Corrección (FR-014/D6): evento nuevo enlazado al original vía `corrige_fichaje_id`, con
     * motivo y autor obligatorios. Nunca toca el original (append-only). Sin verificación de
     * ubicación: es una corrección administrativa de tipo/hora, no un fichaje real.
     */
    public function corregir(Fichaje $original, TipoEventoFichaje $tipo, Carbon $ocurridoAt, string $motivo, User $registradoPor): Fichaje
    {
        return Fichaje::create([
            'tenant_id' => $original->tenant_id,
            'miembro_equipo_id' => $original->miembro_equipo_id,
            'tipo' => $tipo,
            'ocurrido_at' => $ocurridoAt,
            'corrige_fichaje_id' => $original->id,
            'motivo' => $motivo,
            'registrado_por' => $registradoPor->id,
        ]);
    }

    /**
     * @return array{0: ResultadoUbicacionFichaje, 1: int|null}
     */
    private function evaluarUbicacion(MiembroEquipo $miembro, ?float $latitud, ?float $longitud): array
    {
        if ($latitud === null || $longitud === null || ! $miembro->tieneUbicacionTrabajo()) {
            return [ResultadoUbicacionFichaje::SinUbicacion, null];
        }

        $distancia = Haversine::metros(
            (float) $miembro->trabajo_latitud,
            (float) $miembro->trabajo_longitud,
            $latitud,
            $longitud,
        );

        $resultado = $distancia <= $miembro->distancia_max_metros
            ? ResultadoUbicacionFichaje::Dentro
            : ResultadoUbicacionFichaje::Fuera;

        return [$resultado, $distancia];
    }

    /**
     * Deriva el estado de jornada (cerrada/abierta/en_pausa) a partir del último evento real
     * (excluye correcciones, que reescriben hechos pasados, no el estado en vivo) y valida que
     * el tipo solicitado sea coherente (FR-006).
     */
    private function validarSecuencia(MiembroEquipo $miembro, TipoEventoFichaje $tipo): void
    {
        $ultimo = Fichaje::where('miembro_equipo_id', $miembro->id)
            ->whereNull('corrige_fichaje_id')
            ->orderByDesc('ocurrido_at')
            ->orderByDesc('id')
            ->first();

        $estado = match ($ultimo?->tipo) {
            null, TipoEventoFichaje::Salida => 'cerrada',
            TipoEventoFichaje::Entrada, TipoEventoFichaje::FinPausa => 'abierta',
            TipoEventoFichaje::InicioPausa => 'en_pausa',
        };

        $valido = match ($tipo) {
            TipoEventoFichaje::Entrada => $estado === 'cerrada',
            TipoEventoFichaje::Salida => $estado === 'abierta' || $estado === 'en_pausa',
            TipoEventoFichaje::InicioPausa => $estado === 'abierta',
            TipoEventoFichaje::FinPausa => $estado === 'en_pausa',
        };

        if (! $valido) {
            throw new FichajeBloqueadoException(
                "No se puede registrar '{$tipo->label()}' con la jornada en estado '{$estado}'."
            );
        }
    }
}
