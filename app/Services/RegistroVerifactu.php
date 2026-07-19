<?php

namespace App\Services;

use App\Enums\EstadoFactura;
use App\Enums\VerifactuEstado;
use App\Exceptions\CadenaVerifactuRotaException;
use App\Exceptions\VerifactuNoRegistrableException;
use App\Jobs\RemitirRegistroVerifactu;
use App\Models\Factura;
use App\Models\FacturaEvento;
use App\Models\Tenant;
use App\Support\HuellaVerifactu;
use App\Support\QrVerifactu;
use App\Support\ValidadorIdentificacionFiscal;
use App\Support\VerifactuTenant;
use Illuminate\Support\Facades\DB;

/**
 * Único punto del sistema donde se calcula huella y encadenamiento Verifactu (Principio III).
 * Contrato: specs/032-verifactu-registro-aeat/contracts/registro-verifactu.md.
 *
 * El "último eslabón" de la cadena del tenant se busca en `factura_eventos` (no en `facturas`):
 * la cadena real de la AEAT intercala registros de alta Y de anulación, y solo `factura_eventos`
 * tiene una fila por cada uno de los dos (`facturas.huella` es únicamente la del alta de esa
 * factura). Ver docs/03-modelo-datos.md.
 */
class RegistroVerifactu
{
    public function __construct(private readonly GeneradorXmlVerifactu $generadorXml) {}

    /**
     * @throws VerifactuNoRegistrableException
     * @throws CadenaVerifactuRotaException
     */
    public function registrar(Factura $factura): Factura
    {
        if ($factura->estado !== EstadoFactura::Emitida) {
            throw new VerifactuNoRegistrableException('Solo se pueden registrar facturas emitidas.');
        }

        if ($factura->tieneRegistroVerifactu()) {
            throw new VerifactuNoRegistrableException('La factura ya tiene un registro Verifactu; no se puede volver a sellar.');
        }

        if (! ValidadorIdentificacionFiscal::esValido($factura->tenant->nif)) {
            throw new VerifactuNoRegistrableException('El NIF del emisor no es válido: revisa los datos fiscales del tenant.');
        }

        $entorno = VerifactuTenant::entorno($factura->tenant_id);

        // Mutex por tenant (research R2): la fila del tenant existe siempre, incluso antes del
        // primer registro de su cadena, así que sirve de punto de bloqueo aunque todavía no haya
        // ningún eslabón que bloquear directamente.
        Tenant::where('id', $factura->tenant_id)->lockForUpdate()->first();

        $anterior = $this->ultimoEslabon($factura->tenant_id);
        $entornoAnterior = $anterior?->detalle['entorno'] ?? null;

        if ($anterior !== null && $entornoAnterior !== null && $entornoAnterior !== $entorno->value) {
            throw new CadenaVerifactuRotaException('El entorno configurado no coincide con el de la cadena Verifactu existente de este tenant.');
        }

        $huellaAnterior = $anterior->huella ?? '';

        $campos = $this->generadorXml->camposAlta($factura);
        $huella = HuellaVerifactu::alta($campos, $huellaAnterior);
        $xml = $this->generadorXml->xmlAlta($factura, $huella, $huellaAnterior);

        $ahora = now();

        $factura->verifactu_entorno = $entorno;
        $factura->huella = $huella;
        $factura->huella_anterior = $huellaAnterior;
        $factura->registro_xml = $xml;
        $factura->qr_contenido = QrVerifactu::url($factura);
        $factura->verifactu_estado = VerifactuEstado::Registrada;
        $factura->registrada_at = $ahora;
        $factura->save();

        FacturaEvento::create([
            'tenant_id' => $factura->tenant_id,
            'factura_id' => $factura->id,
            'tipo_evento' => 'verifactu_alta',
            'detalle' => ['huella' => $huella, 'huella_anterior' => $huellaAnterior, 'entorno' => $entorno->value],
            'huella' => $huella,
            'ocurrido_at' => $ahora,
        ]);

        return $factura->refresh();
    }

    /**
     * Registro de anulación encadenado (research R1/R7). No modifica `facturas.huella` ni
     * `facturas.huella_anterior` (esos siguen siendo, para siempre, los del registro de alta):
     * la huella de la anulación se guarda únicamente en el evento `verifactu_anulacion`.
     *
     * @throws VerifactuNoRegistrableException
     */
    public function registrarAnulacion(Factura $factura, string $motivo): Factura
    {
        if (! $factura->tieneRegistroVerifactu()) {
            throw new VerifactuNoRegistrableException('Solo se puede anular el registro Verifactu de una factura ya registrada.');
        }

        DB::transaction(function () use ($factura, $motivo) {
            Tenant::where('id', $factura->tenant_id)->lockForUpdate()->first();

            $anterior = $this->ultimoEslabon($factura->tenant_id);
            $huellaAnterior = $anterior->huella ?? '';

            $campos = $this->generadorXml->camposAnulacion($factura);
            $huella = HuellaVerifactu::anulacion($campos, $huellaAnterior);
            $xml = $this->generadorXml->xmlAnulacion($factura, $huella, $huellaAnterior, $motivo);

            FacturaEvento::create([
                'tenant_id' => $factura->tenant_id,
                'factura_id' => $factura->id,
                'tipo_evento' => 'verifactu_anulacion',
                'detalle' => [
                    'huella' => $huella,
                    'huella_anterior' => $huellaAnterior,
                    'motivo' => $motivo,
                    'entorno' => $factura->verifactu_entorno->value,
                    'xml' => $xml,
                ],
                'huella' => $huella,
                'ocurrido_at' => now(),
            ]);

            DB::afterCommit(fn () => RemitirRegistroVerifactu::dispatch($factura->id, 'anulacion'));
        });

        return $factura;
    }

    private function ultimoEslabon(int $tenantId): ?FacturaEvento
    {
        return FacturaEvento::where('tenant_id', $tenantId)
            ->whereIn('tipo_evento', ['verifactu_alta', 'verifactu_anulacion'])
            ->whereNotNull('huella')
            ->orderByDesc('ocurrido_at')
            ->orderByDesc('id')
            ->first();
    }
}
