<?php

namespace App\Services;

use App\Enums\EstadoFactura;
use App\Enums\FormaPago;
use App\Models\CajaMovimiento;
use App\Models\CajaSesion;
use App\Support\DenominacionesEuro;
use Illuminate\Support\Facades\DB;

/**
 * El cálculo de lo que pasó en una sesión de caja (feature 048, research D5).
 *
 * **Un solo cálculo para todo**: la pantalla en vivo (informe X), el cierre (que congela esta misma
 * salida en la sesión) y por tanto el informe Z y su PDF. Si cada uno sumara por su cuenta,
 * acabarían contradiciéndose (docs/04 "Dos vistas de los mismos datos").
 *
 * Agregados SQL, nunca un modelo por ticket. Toda la aritmética en céntimos enteros; la salida en
 * strings con 2 decimales, igual que viajan los importes en el resto del POS.
 *
 * Los tickets de la sesión son los que tienen algún `ticket_pagos.caja_sesion_id` = sesión (D1). Un
 * ticket anulado no suma a ventas ni al efectivo esperado: va a su lista propia.
 */
class ResumenCaja
{
    /** Orden de lectura en caja: lo que se arquea primero. */
    private const ORDEN_METODOS = [FormaPago::Efectivo, FormaPago::Tarjeta, FormaPago::Transferencia, FormaPago::Domiciliacion];

    /**
     * @return array{
     *     num_tickets: int,
     *     total_facturado: string,
     *     ticket_medio: string,
     *     efectivo_ventas: string,
     *     entradas: string,
     *     salidas: string,
     *     fondo_inicial: string,
     *     efectivo_esperado: string,
     *     resumen: array{
     *         por_metodo: list<array{metodo: string, label: string, importe: string, tickets: int}>,
     *         por_impuesto: list<array{tipo_impuesto: string, porcentaje: string, base: string, cuota: string}>,
     *         primer_ticket: ?string,
     *         ultimo_ticket: ?string,
     *         anulados: list<array{numero: ?string, total: string}>,
     *         movimientos: list<array{tipo: string, importe: string, motivo: string, usuario: ?string, at: string}>
     *     }
     * }
     */
    public function calcular(CajaSesion $sesion): array
    {
        $tenantId = (int) $sesion->tenant_id;

        $tickets = DB::table('facturas')
            ->where('facturas.tenant_id', $tenantId)
            ->whereIn('facturas.id', function ($q) use ($tenantId, $sesion) {
                $q->select('factura_id')->from('ticket_pagos')
                    ->where('tenant_id', $tenantId)
                    ->where('caja_sesion_id', $sesion->id);
            })
            ->orderBy('facturas.id')
            ->get(['facturas.id', 'facturas.numero_completo', 'facturas.total', 'facturas.estado']);

        $anulada = EstadoFactura::Anulada->value;
        $vigentes = $tickets->reject(fn ($t) => $t->estado === $anulada);
        $idsVigentes = $vigentes->pluck('id')->all();

        $totalCent = $vigentes->sum(fn ($t) => DenominacionesEuro::aCentimos($t->total));

        // Por método: los cuatro siempre presentes, para que el informe tenga siempre la misma forma.
        $metodos = DB::table('ticket_pagos')
            ->where('tenant_id', $tenantId)
            ->where('caja_sesion_id', $sesion->id)
            ->whereIn('factura_id', $idsVigentes ?: [0])
            ->groupBy('metodo')
            ->selectRaw('metodo, SUM(importe) as importe, COUNT(DISTINCT factura_id) as tickets')
            ->get()
            ->keyBy('metodo');

        $porMetodo = [];
        $efectivoCent = 0;
        foreach (self::ORDEN_METODOS as $metodo) {
            $fila = $metodos->get($metodo->value);
            $cent = $fila ? DenominacionesEuro::aCentimos($fila->importe) : 0;
            if ($metodo === FormaPago::Efectivo) {
                $efectivoCent = $cent;
            }
            $porMetodo[] = [
                'metodo' => $metodo->value,
                'label' => self::etiquetaMetodo($metodo),
                'importe' => DenominacionesEuro::aDecimal($cent),
                'tickets' => $fila ? (int) $fila->tickets : 0,
            ];
        }

        $porImpuesto = DB::table('factura_impuestos')
            ->where('tenant_id', $tenantId)
            ->whereIn('factura_id', $idsVigentes ?: [0])
            ->groupBy('tipo_impuesto', 'porcentaje')
            ->orderBy('tipo_impuesto')
            ->orderByDesc('porcentaje')
            ->selectRaw('tipo_impuesto, porcentaje, SUM(base_imponible) as base, SUM(cuota) as cuota')
            ->get()
            ->map(fn ($f) => [
                'tipo_impuesto' => (string) $f->tipo_impuesto,
                'porcentaje' => number_format((float) $f->porcentaje, 2, '.', ''),
                'base' => DenominacionesEuro::aDecimal(DenominacionesEuro::aCentimos($f->base)),
                'cuota' => DenominacionesEuro::aDecimal(DenominacionesEuro::aCentimos($f->cuota)),
            ])
            ->values()
            ->all();

        $movimientos = CajaMovimiento::query()
            ->where('tenant_id', $tenantId)
            ->where('caja_sesion_id', $sesion->id)
            ->with('usuario:id,name')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $entradasCent = 0;
        $salidasCent = 0;
        foreach ($movimientos as $mov) {
            $cent = DenominacionesEuro::aCentimos($mov->importe);
            $mov->tipo === CajaMovimiento::TIPO_ENTRADA ? $entradasCent += $cent : $salidasCent += $cent;
        }

        $fondoCent = DenominacionesEuro::aCentimos($sesion->fondo_inicial);
        $numTickets = $vigentes->count();

        return [
            'num_tickets' => $numTickets,
            'total_facturado' => DenominacionesEuro::aDecimal($totalCent),
            'ticket_medio' => DenominacionesEuro::aDecimal($numTickets > 0 ? (int) round($totalCent / $numTickets) : 0),
            'efectivo_ventas' => DenominacionesEuro::aDecimal($efectivoCent),
            'entradas' => DenominacionesEuro::aDecimal($entradasCent),
            'salidas' => DenominacionesEuro::aDecimal($salidasCent),
            'fondo_inicial' => DenominacionesEuro::aDecimal($fondoCent),
            'efectivo_esperado' => DenominacionesEuro::aDecimal($fondoCent + $efectivoCent + $entradasCent - $salidasCent),
            'resumen' => [
                'por_metodo' => $porMetodo,
                'por_impuesto' => $porImpuesto,
                'primer_ticket' => $tickets->first()?->numero_completo,
                'ultimo_ticket' => $tickets->last()?->numero_completo,
                'anulados' => $tickets->filter(fn ($t) => $t->estado === $anulada)->map(fn ($t) => [
                    'numero' => $t->numero_completo,
                    'total' => DenominacionesEuro::aDecimal(DenominacionesEuro::aCentimos($t->total)),
                ])->values()->all(),
                'movimientos' => $movimientos->map(fn (CajaMovimiento $m) => [
                    'tipo' => $m->tipo,
                    'importe' => DenominacionesEuro::aDecimal(DenominacionesEuro::aCentimos($m->importe)),
                    'motivo' => $m->motivo,
                    'usuario' => $m->usuario?->name,
                    'at' => $m->created_at->toIso8601String(),
                ])->values()->all(),
            ],
        ];
    }

    public static function etiquetaMetodo(FormaPago|string $metodo): string
    {
        $valor = $metodo instanceof FormaPago ? $metodo->value : $metodo;

        return match ($valor) {
            'efectivo' => __('Efectivo'),
            'tarjeta' => __('Tarjeta'),
            'transferencia' => __('Transferencia'),
            'domiciliacion' => __('Domiciliación'),
            default => ucfirst($valor),
        };
    }
}
