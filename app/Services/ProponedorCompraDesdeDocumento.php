<?php

namespace App\Services;

use App\Models\Compra;
use App\Support\EmparejadorArticulo;
use App\Support\EmparejadorProveedor;
use App\Support\TiposImpositivos;

/**
 * Traduce la lectura cruda del modelo (`contracts/propuesta-compra.schema.json`) a la propuesta de
 * negocio que revisa el usuario (`contracts/propuesta-ui.schema.json`).
 *
 * Dos garantías que sostienen la feature:
 *
 * - **Los importes los calcula el servidor** (Principio III). Lo que diga el documento sobre el
 *   total solo se conserva para enseñar la discrepancia; jamás alimenta un cálculo.
 * - **El impuesto no se asume IVA** (Principio II). Un tipo que el documento no indica queda
 *   vacío y señalado como ilegible; nunca se rellena con el tipo por defecto del régimen.
 *
 * No habla con el proveedor de IA: recibe la lectura ya hecha, y por eso sus pruebas corren sin
 * red ni clave (research D3).
 */
class ProponedorCompraDesdeDocumento
{
    /**
     * @param  array<string, mixed>  $lectura
     * @return array<string, mixed>
     */
    public function proponer(array $lectura, string $token, string $archivoNombre): array
    {
        $ilegibles = [];
        $avisos = [];

        foreach (['numero_documento', 'fecha'] as $campo) {
            if (($lectura[$campo] ?? null) === null || $lectura[$campo] === '') {
                $ilegibles[] = $campo;
            }
        }

        $proveedor = (new EmparejadorProveedor)->emparejar($lectura['emisor'] ?? null);
        $regimen = tenant()->regimen_impositivo;
        // Se instancia una vez fuera del bucle: cada emparejamiento relee el catálogo del tenant.
        $emparejadorArticulo = new EmparejadorArticulo;
        $lineas = [];
        $baseTotal = 0.0;
        $cuotaTotal = 0.0;
        $hayTipoIncoherente = false;

        foreach (($lectura['lineas'] ?? []) as $indice => $cruda) {
            foreach (['cantidad', 'precio_unitario', 'tipo_impositivo', 'unidad'] as $campo) {
                if (($cruda[$campo] ?? null) === null || $cruda[$campo] === '') {
                    $ilegibles[] = "lineas.{$indice}.{$campo}";
                }
            }

            $cantidad = $cruda['cantidad'] ?? null;
            $precio = $cruda['precio_unitario'] ?? null;
            $tipo = $cruda['tipo_impositivo'] ?? null;

            // Misma fórmula que el alta manual (CompraController::guardar), para que una compra
            // importada y una tecleada den exactamente el mismo resultado.
            $base = round((float) $cantidad * (float) $precio, 2);
            $cuota = $tipo === null ? 0.0 : round($base * (float) $tipo / 100, 2);

            $baseTotal += $base;
            $cuotaTotal += $cuota;

            // Informativo: avisa de una lectura sospechosa (un 21 % en un tenant de IGIC suele ser
            // IVA leído por error). No bloquea — el usuario puede confirmarlo igual.
            $coherente = $tipo === null || TiposImpositivos::esTipoHabitual($regimen, (float) $tipo);

            if (! $coherente) {
                $hayTipoIncoherente = true;
            }

            $lineas[] = [
                'concepto' => $cruda['concepto'] ?? '',
                'referencia' => $cruda['referencia'] ?? null,
                'unidad' => $cruda['unidad'] ?? null,
                'cantidad' => $cantidad === null ? null : (float) $cantidad,
                'precio_unitario' => $precio === null ? null : (float) $precio,
                'tipo_impositivo' => $tipo === null ? null : (float) $tipo,
                'tipo_impositivo_coherente' => $coherente,
                'articulo' => $emparejadorArticulo->emparejar($cruda),
                'base' => $base,
                'cuota_impuesto' => $cuota,
            ];
        }

        $baseTotal = round($baseTotal, 2);
        $cuotaTotal = round($cuotaTotal, 2);
        $total = round($baseTotal + $cuotaTotal, 2);

        $totalDocumento = isset($lectura['total_documento']) ? (float) $lectura['total_documento'] : null;

        if ($totalDocumento !== null && abs($totalDocumento - $total) >= 0.01) {
            $avisos[] = [
                'tipo' => 'totales_no_cuadran',
                'mensaje' => 'El total calculado ('.number_format($total, 2, ',', '.')
                    .' €) no coincide con el que figura en el documento ('
                    .number_format($totalDocumento, 2, ',', '.').' €). Revisá las líneas.',
            ];
        }

        if ($hayTipoIncoherente) {
            $avisos[] = [
                'tipo' => 'tipo_impositivo_incoherente',
                'mensaje' => 'Alguna línea tiene un tipo impositivo que no es habitual en el régimen '
                    .$regimen->label().' de tu empresa. Revisalo antes de confirmar.',
            ];
        }

        // El duplicado se evalúa dos veces: aquí para avisar antes de que el usuario edite nada, y
        // otra vez al crear, porque entre medias otro usuario pudo subir el mismo documento.
        $duplicada = $this->buscarDuplicada($proveedor['proveedor_id'] ?? null, $lectura['numero_documento'] ?? null, $lectura['fecha'] ?? null);

        if ($duplicada) {
            $avisos[] = [
                'tipo' => 'posible_duplicado',
                'mensaje' => 'Ya existe una compra con el mismo proveedor, número y fecha. Revisá que no la estés cargando dos veces.',
                'url' => route('compras.show', $duplicada),
            ];
        }

        $moneda = $lectura['moneda'] ?? null;

        if ($moneda !== null && strtoupper((string) $moneda) !== 'EUR') {
            $avisos[] = [
                'tipo' => 'moneda_no_eur',
                'mensaje' => 'El documento parece estar en '.strtoupper((string) $moneda)
                    .'. Los importes se registran tal cual, sin convertir.',
            ];
        }

        return [
            'token' => $token,
            'archivo_nombre' => $archivoNombre,
            'proveedor' => $proveedor,
            'numero_documento' => $lectura['numero_documento'] ?? null,
            'fecha' => $lectura['fecha'] ?? null,
            'notas' => null,
            'lineas' => $lineas,
            'campos_ilegibles' => $ilegibles,
            'avisos' => $avisos,
            'totales' => [
                'base_total' => $baseTotal,
                'cuota_impuesto_total' => $cuotaTotal,
                'total' => $total,
            ],
            'total_documento' => $totalDocumento,
        ];
    }

    /**
     * Mismo criterio que `ImportadorFacturae`: proveedor + número + fecha del mismo día. Solo se
     * puede evaluar si el proveedor quedó resuelto; con uno nuevo no hay nada que duplicar.
     */
    private function buscarDuplicada(?int $proveedorId, ?string $numero, ?string $fecha): ?Compra
    {
        if (! $proveedorId || ! $numero || ! $fecha) {
            return null;
        }

        return Compra::where('proveedor_id', $proveedorId)
            ->where('numero_documento', $numero)
            ->whereDate('fecha', $fecha)
            ->first();
    }
}
