<?php

namespace App\Services;

use App\Enums\EstadoLead;
use App\Enums\OrigenLead;
use App\Models\CanalCaptacion;
use App\Models\Lead;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Único punto de importación de leads por fichero (research D2): CSV/XLSX síncrono, sin colas
 * (Principio V). Nunca aborta por filas inválidas — cada fila rechazada se reporta con su motivo,
 * las válidas se persisten igual (FR-003, "sin importación parcial silenciosa" = sin silenciar
 * rechazos, no "todo o nada").
 */
class ImportadorLeads
{
    public function __construct(private readonly AsignadorLeads $asignador) {}

    public function importar(UploadedFile $fichero, int $tenantId): ResultadoImportacionLeads
    {
        $hojas = Excel::toArray(new class implements WithHeadingRow {}, $fichero);
        $filas = $hojas[0] ?? [];

        $importados = 0;
        $rechazadas = [];
        $avisos = [];
        $canalesPorNombre = null;

        foreach ($filas as $indice => $fila) {
            $numeroFila = $indice + 2; // fila 1 = cabecera, filas de datos empiezan en la 2

            $nombre = trim((string) ($fila['nombre'] ?? ''));
            $empresa = trim((string) ($fila['empresa'] ?? '')) ?: null;
            $email = trim((string) ($fila['email'] ?? '')) ?: null;
            $telefono = trim((string) ($fila['telefono'] ?? '')) ?: null;
            $nombreCanal = trim((string) ($fila['canal'] ?? '')) ?: null;

            if ($nombre === '') {
                $rechazadas[] = ['fila' => $numeroFila, 'motivo' => 'Falta el nombre'];

                continue;
            }

            if ($email === null && $telefono === null) {
                $rechazadas[] = ['fila' => $numeroFila, 'motivo' => 'Falta email o teléfono'];

                continue;
            }

            if ($this->esDuplicado($tenantId, $email, $telefono)) {
                $rechazadas[] = ['fila' => $numeroFila, 'motivo' => 'duplicado'];

                continue;
            }

            $canalId = null;

            if ($nombreCanal !== null) {
                $canalesPorNombre ??= $this->canalesPorNombre($tenantId);
                $clave = mb_strtolower($nombreCanal);

                if (isset($canalesPorNombre[$clave])) {
                    $canalId = $canalesPorNombre[$clave];
                } else {
                    $avisos[] = ['fila' => $numeroFila, 'motivo' => "Canal «{$nombreCanal}» desconocido: la fila se importó sin canal"];
                }
            }

            Lead::create([
                'tenant_id' => $tenantId,
                'nombre' => $nombre,
                'empresa' => $empresa,
                'email' => $email,
                'telefono' => $telefono,
                'estado' => EstadoLead::Nuevo,
                'origen' => OrigenLead::Importacion,
                'canal_captacion_id' => $canalId,
                'asignado_a' => $this->asignador->asignar($tenantId),
            ]);

            $importados++;
        }

        return new ResultadoImportacionLeads($importados, $rechazadas, $avisos);
    }

    /**
     * @return array<string, int> nombre de canal (minúsculas) => id
     */
    private function canalesPorNombre(int $tenantId): array
    {
        return CanalCaptacion::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->get(['id', 'nombre'])
            ->mapWithKeys(fn (CanalCaptacion $canal) => [mb_strtolower($canal->nombre) => $canal->id])
            ->all();
    }

    private function esDuplicado(int $tenantId, ?string $email, ?string $telefono): bool
    {
        if ($email === null && $telefono === null) {
            return false;
        }

        return Lead::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where(function ($query) use ($email, $telefono) {
                if ($email !== null) {
                    $query->orWhere('email', $email);
                }
                if ($telefono !== null) {
                    $query->orWhere('telefono', $telefono);
                }
            })
            ->exists();
    }
}
