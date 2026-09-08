<?php

namespace App\Excel\Informes;

use App\Models\CanalCaptacion;
use App\Models\User;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Exportador multi-hoja del informe comercial (research D7, feature 033): una hoja de portada
 * (periodo, filtros, alcance) y una hoja por bloque visible. Los bloques ocultos por permisos no
 * se escriben (FR-028).
 */
class InformeComercialExport implements WithMultipleSheets
{
    private const ETIQUETAS_INDICADORES = [
        'leads_captados' => 'Leads captados',
        'leads_convertidos' => 'Leads convertidos',
        'oportunidades_creadas' => 'Oportunidades creadas',
        'oportunidades_ganadas' => 'Oportunidades ganadas',
        'oportunidades_perdidas' => 'Oportunidades perdidas',
        'oportunidades_abiertas' => 'Oportunidades abiertas',
        'importe_pipeline' => 'Importe en pipeline (€)',
        'presupuestos_emitidos' => 'Presupuestos emitidos',
        'importe_presupuestado' => 'Importe presupuestado (€)',
        'presupuestos_aceptados' => 'Presupuestos aceptados',
        'importe_aceptado' => 'Importe aceptado (€)',
        'facturas_del_embudo' => 'Negocio cerrado del embudo (facturas)',
        'importe_facturado_embudo' => 'Importe facturado del embudo (€)',
    ];

    private const ETIQUETAS_RATIOS = [
        'conversion_lead_cliente' => 'Conversión lead → cliente (%)',
        'oportunidades_ganadas' => 'Oportunidades ganadas sobre cerradas (%)',
        'aceptacion_presupuestos' => 'Aceptación de presupuestos (%)',
        'conversion_presupuesto_factura' => 'Conversión presupuesto → factura (%)',
        'importe_medio_ganada' => 'Importe medio de oportunidad ganada (€)',
        'ciclo_medio_lead_dias' => 'Ciclo medio lead → cliente (días)',
        'ciclo_medio_oportunidad_dias' => 'Ciclo medio de oportunidad (días)',
    ];

    public function __construct(private readonly array $datos) {}

    /**
     * @return list<HojaInformeComercial>
     */
    public function sheets(): array
    {
        $bloques = $this->datos['alcance']['bloques_visibles'];

        $hojas = [new HojaInformeComercial('Portada', ['Campo', 'Valor'], $this->filasPortada())];

        if (in_array('leads', $bloques, true)) {
            $hojas[] = new HojaInformeComercial('Leads', ['Indicador', 'Valor'], $this->filasBloque([
                'leads_captados', 'leads_convertidos',
            ], ['conversion_lead_cliente', 'ciclo_medio_lead_dias'], 'leads_por_estado', 'etiqueta', 'cantidad'));
        }

        if (in_array('oportunidades', $bloques, true)) {
            $hojas[] = new HojaInformeComercial('Oportunidades', ['Indicador', 'Valor'], $this->filasBloque([
                'oportunidades_creadas', 'oportunidades_ganadas', 'oportunidades_perdidas',
                'oportunidades_abiertas', 'importe_pipeline',
            ], ['oportunidades_ganadas', 'importe_medio_ganada', 'ciclo_medio_oportunidad_dias'], 'oportunidades_por_etapa', 'etiqueta', 'cantidad'));
        }

        if (in_array('presupuestos', $bloques, true)) {
            $hojas[] = new HojaInformeComercial('Presupuestos', ['Indicador', 'Valor'], $this->filasBloque([
                'presupuestos_emitidos', 'importe_presupuestado', 'presupuestos_aceptados', 'importe_aceptado',
                'facturas_del_embudo', 'importe_facturado_embudo',
            ], ['aceptacion_presupuestos', 'conversion_presupuesto_factura'], 'presupuestos_por_estado', 'etiqueta', 'cantidad'));
        }

        $hojas[] = new HojaInformeComercial(
            'Evolución',
            ['Periodo', 'Leads', 'Oportunidades', 'Presupuestos'],
            collect($this->datos['evolucion'])->map(fn (array $p) => [
                $p['etiqueta'], self::celda($p['leads']), self::celda($p['oportunidades']), self::celda($p['presupuestos']),
            ])->all(),
        );

        if (in_array('presupuestos', $bloques, true) && ! empty($this->datos['top_articulos'])) {
            $hojas[] = new HojaInformeComercial(
                'Top artículos',
                ['Artículo', 'Importe (€)', 'Unidades'],
                collect($this->datos['top_articulos'])->map(fn (array $a) => [$a['nombre'], self::celda($a['importe']), self::celda($a['unidades'])])->all(),
            );
        }

        if ($this->datos['comparativa'] !== null) {
            $hojas[] = new HojaInformeComercial('Comparativa', ['Indicador', 'Actual', 'Ejercicio anterior', 'Variación (%)'], $this->filasComparativa());
        }

        return $hojas;
    }

    /**
     * @return list<array{0: string, 1: mixed}>
     */
    private function filasPortada(): array
    {
        $filtros = $this->datos['filtros'];

        $canal = $filtros['canal_id'] === null
            ? 'Todos'
            : ($filtros['canal_id'] === 'sin_especificar' ? 'Sin especificar' : (CanalCaptacion::find($filtros['canal_id'])->nombre ?? 'Canal eliminado'));

        $comercial = $filtros['comercial_id'] === null
            ? 'Todos'
            : (User::find($filtros['comercial_id'])->name ?? 'Usuario eliminado');

        return [
            ['Periodo', $this->datos['periodo']['etiqueta']],
            ['Desde', $this->datos['periodo']['desde']],
            ['Hasta', $this->datos['periodo']['hasta']],
            ['Alcance', $this->datos['alcance']['tipo'] === 'tenant' ? 'Todo el tenant' : 'Actividad propia'],
            ['Canal', $canal],
            ['Comercial', $comercial],
            ['Fase', $filtros['fase'] ?? 'Todas'],
            ['Comparativa entre ejercicios', $filtros['comparar'] ? 'Activada' : 'Desactivada'],
        ];
    }

    /**
     * @param  list<string>  $clavesIndicadores
     * @param  list<string>  $clavesRatios
     * @return list<array{0: string, 1: mixed}>
     */
    private function filasBloque(array $clavesIndicadores, array $clavesRatios, string $claveFases, string $etiquetaFase, string $cantidadFase): array
    {
        $filas = [];

        foreach ($clavesIndicadores as $clave) {
            $filas[] = [self::ETIQUETAS_INDICADORES[$clave], self::celda($this->datos['indicadores'][$clave])];
        }

        foreach ($clavesRatios as $clave) {
            $valor = $this->datos['ratios'][$clave];
            $filas[] = [self::ETIQUETAS_RATIOS[$clave], $valor === null ? 'Sin datos' : self::celda($valor)];
        }

        foreach ($this->datos['fases'][$claveFases] as $fila) {
            $filas[] = [$fila[$etiquetaFase], self::celda($fila[$cantidadFase])];
        }

        return $filas;
    }

    /**
     * @return list<array{0: string, 1: mixed, 2: mixed, 3: mixed}>
     */
    private function filasComparativa(): array
    {
        $comparativa = $this->datos['comparativa'];
        $filas = [];

        foreach (self::ETIQUETAS_INDICADORES as $clave => $etiqueta) {
            $variacion = $comparativa['variaciones']['indicadores'][$clave] ?? null;

            $filas[] = [
                $etiqueta,
                self::celda($this->datos['indicadores'][$clave]),
                self::celda($comparativa['indicadores'][$clave]),
                $variacion === null ? 'N/A' : self::celda($variacion),
            ];
        }

        foreach (self::ETIQUETAS_RATIOS as $clave => $etiqueta) {
            $actual = $this->datos['ratios'][$clave];
            $anterior = $comparativa['ratios'][$clave];
            $variacion = $comparativa['variaciones']['ratios'][$clave] ?? null;

            $filas[] = [
                $etiqueta,
                $actual === null ? 'Sin datos' : self::celda($actual),
                $anterior === null ? 'Sin datos' : self::celda($anterior),
                $variacion === null ? 'N/A' : self::celda($variacion),
            ];
        }

        return $filas;
    }

    /**
     * Fuerza los valores numéricos a string antes de escribirlos: Laravel Excel/PhpSpreadsheet
     * descarta un `0`/`0.0` literal (PHP) al volcarlo a una celda con `FromArray`, y el valor
     * vuelve `NULL` al releerlo — comprobado empíricamente al construir esta clase. Como esta
     * hoja es solo para lectura humana (no para recalcular), forzar string es seguro y evita el
     * bug sin tocar la librería.
     */
    private static function celda(int|float $valor): string
    {
        return (string) $valor;
    }
}
