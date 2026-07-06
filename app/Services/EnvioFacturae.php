<?php

namespace App\Services;

use App\Exceptions\CertificadoInvalidoException;
use App\Exceptions\EmailNoConfiguradoException;
use App\Exceptions\FacturaeNoGenerableException;
use App\Mail\FacturaMail;
use App\Models\Factura;
use App\Models\FacturaEvento;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Orquesta generar (o reutilizar) el Facturae de una factura, conservarlo y enviarlo por email al
 * cliente, reutilizando `TenantMailer`/`FacturaMail` (feature 017). Degrada con aviso si no hay
 * email/SMTP o el envío falla: el XML queda generado y conservado (FR-006a), no se pierde.
 */
class EnvioFacturae
{
    public function __construct(private readonly GeneradorFacturae $generador) {}

    /**
     * @return array{aviso: string|null}
     *
     * @throws FacturaeNoGenerableException
     * @throws CertificadoInvalidoException
     */
    public function generarYEnviar(Factura $factura, ?string $destinatario = null): array
    {
        $resultado = $this->generador->generarYConservar($factura);

        if ($resultado['regenerado']) {
            FacturaEvento::create([
                'tenant_id' => $factura->tenant_id,
                'factura_id' => $factura->id,
                'tipo_evento' => 'facturae_generado',
                'detalle' => ['ruta' => $resultado['ruta']],
                'ocurrido_at' => now(),
            ]);
        }

        $destino = $destinatario ?: $factura->cliente?->email;

        if (! $destino) {
            return ['aviso' => 'El Facturae se generó y se guardó, pero el cliente no tiene email: el envío quedó pendiente.'];
        }

        try {
            $tenantMailer = new TenantMailer($factura->tenant_id);
        } catch (EmailNoConfiguradoException) {
            return ['aviso' => 'El Facturae se generó y se guardó, pero el correo del tenant no está configurado: el envío quedó pendiente.'];
        }

        return $this->enviarConMailer($factura, $resultado['xml'], $destino, $tenantMailer);
    }

    /**
     * Reenvía el Facturae ya generado (sin regenerarlo).
     *
     * @throws FacturaeNoGenerableException si aún no se ha generado
     * @throws EmailNoConfiguradoException
     */
    public function reenviar(Factura $factura, string $destinatario): void
    {
        $xml = $this->generador->leerConservado($factura);

        if ($xml === null) {
            throw new FacturaeNoGenerableException('Aún no se ha generado el Facturae de esta factura.');
        }

        $tenantMailer = new TenantMailer($factura->tenant_id);

        $this->enviarConMailer($factura, $xml, $destinatario, $tenantMailer);
    }

    /**
     * @return array{aviso: string|null}
     */
    private function enviarConMailer(Factura $factura, string $xml, string $destino, TenantMailer $tenantMailer): array
    {
        $pdf = Pdf::loadView('facturas.pdf', ['factura' => $factura])->output();

        $mailable = (new FacturaMail($factura, $pdf, $xml))
            ->from($tenantMailer->remitente(), $tenantMailer->remitenteNombre());

        if ($tenantMailer->responderA()) {
            $mailable->replyTo($tenantMailer->responderA());
        }

        try {
            $tenantMailer->mailer()->to($destino)->send($mailable);
        } catch (\Throwable $e) {
            FacturaEvento::create([
                'tenant_id' => $factura->tenant_id,
                'factura_id' => $factura->id,
                'tipo_evento' => 'envio_facturae',
                'detalle' => ['destinatario' => $destino, 'resultado' => 'error', 'error' => $e->getMessage()],
                'ocurrido_at' => now(),
            ]);

            return ['aviso' => 'El Facturae se generó y se guardó, pero no se pudo enviar el correo: el envío quedó pendiente.'];
        }

        FacturaEvento::create([
            'tenant_id' => $factura->tenant_id,
            'factura_id' => $factura->id,
            'tipo_evento' => 'envio_facturae',
            'detalle' => ['destinatario' => $destino, 'resultado' => 'ok'],
            'ocurrido_at' => now(),
        ]);

        return ['aviso' => null];
    }
}
