<?php

namespace Database\Seeders;

use App\Models\Configuracion;
use App\Models\Tenant;
use App\Support\AparienciaTenant;
use App\Support\ArchivosTenant;
use App\Support\EmailTenant;
use App\Support\TopeSimplificada;
use Illuminate\Database\Seeder;

class ConfiguracionSeeder extends Seeder
{
    /**
     * Valores de configuración por defecto del tenant demo. Usamos firstOrCreate por
     * (tenant_id, clave) para no pisar valores ya modificados por el usuario en re-seeds.
     */
    public function run(): void
    {
        $tenant = Tenant::firstWhere('nombre_comercial', 'Empresa Demo SL');

        if (! $tenant) {
            return;
        }

        $configuraciones = [
            [
                'clave' => 'apariencia.color_primario',
                'valor' => AparienciaTenant::DEFAULT_PRIMARIO,
                'tipo' => 'string',
                'grupo' => 'apariencia',
                'descripcion' => null,
            ],
            [
                'clave' => 'apariencia.color_secundario',
                'valor' => AparienciaTenant::DEFAULT_SECUNDARIO,
                'tipo' => 'string',
                'grupo' => 'apariencia',
                'descripcion' => null,
            ],
            [
                'clave' => 'apariencia.color_topbar',
                'valor' => AparienciaTenant::DEFAULT_TOPBAR,
                'tipo' => 'string',
                'grupo' => 'apariencia',
                'descripcion' => null,
            ],
            [
                'clave' => 'apariencia.facebook_url',
                'valor' => AparienciaTenant::DEFAULT_FACEBOOK,
                'tipo' => 'string',
                'grupo' => 'apariencia',
                'descripcion' => null,
            ],
            [
                'clave' => 'apariencia.instagram_url',
                'valor' => AparienciaTenant::DEFAULT_INSTAGRAM,
                'tipo' => 'string',
                'grupo' => 'apariencia',
                'descripcion' => null,
            ],
            [
                'clave' => 'apariencia.titulo_login',
                'valor' => AparienciaTenant::DEFAULT_TITULO_LOGIN,
                'tipo' => 'string',
                'grupo' => 'apariencia',
                'descripcion' => null,
            ],
            [
                'clave' => TopeSimplificada::CLAVE,
                'valor' => '0',
                'tipo' => 'boolean',
                'grupo' => 'facturacion',
                'descripcion' => 'Sector con tope ampliado de factura simplificada (3.000 € en vez de 400 €).',
            ],
            [
                'clave' => EmailTenant::CLAVE_SMTP_HOST,
                'valor' => EmailTenant::DEFAULT_SMTP_HOST,
                'tipo' => 'string',
                'grupo' => 'email',
                'descripcion' => null,
            ],
            [
                'clave' => EmailTenant::CLAVE_SMTP_PORT,
                'valor' => EmailTenant::DEFAULT_SMTP_PORT,
                'tipo' => 'integer',
                'grupo' => 'email',
                'descripcion' => null,
            ],
            [
                'clave' => EmailTenant::CLAVE_SMTP_ENCRYPTION,
                'valor' => EmailTenant::DEFAULT_SMTP_ENCRYPTION,
                'tipo' => 'string',
                'grupo' => 'email',
                'descripcion' => null,
            ],
            [
                'clave' => EmailTenant::CLAVE_SMTP_USUARIO,
                'valor' => EmailTenant::DEFAULT_SMTP_USUARIO,
                'tipo' => 'string',
                'grupo' => 'email',
                'descripcion' => null,
            ],
            [
                'clave' => EmailTenant::CLAVE_SMTP_PASSWORD,
                'valor' => EmailTenant::DEFAULT_SMTP_PASSWORD,
                'tipo' => 'string',
                'grupo' => 'email',
                'descripcion' => null,
            ],
            [
                'clave' => EmailTenant::CLAVE_REMITENTE,
                'valor' => EmailTenant::DEFAULT_REMITENTE,
                'tipo' => 'string',
                'grupo' => 'email',
                'descripcion' => null,
            ],
            [
                'clave' => EmailTenant::CLAVE_REMITENTE_NOMBRE,
                'valor' => EmailTenant::DEFAULT_REMITENTE_NOMBRE,
                'tipo' => 'string',
                'grupo' => 'email',
                'descripcion' => null,
            ],
            [
                'clave' => EmailTenant::CLAVE_RESPONDER_A,
                'valor' => EmailTenant::DEFAULT_RESPONDER_A,
                'tipo' => 'string',
                'grupo' => 'email',
                'descripcion' => null,
            ],
            [
                'clave' => ArchivosTenant::CLAVE_LIMITE_MB,
                'valor' => (string) ArchivosTenant::DEFAULT_LIMITE_MB,
                'tipo' => 'integer',
                'grupo' => 'archivos',
                'descripcion' => 'Tamaño máximo por archivo subido (MB).',
            ],
        ];

        foreach ($configuraciones as $configuracion) {
            Configuracion::firstOrCreate(
                ['tenant_id' => $tenant->id, 'clave' => $configuracion['clave']],
                $configuracion
            );
        }
    }
}
