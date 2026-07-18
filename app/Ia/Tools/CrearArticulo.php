<?php

namespace App\Ia\Tools;

use App\Enums\TipoArticulo;
use App\Models\Articulo;
use Illuminate\Validation\ValidationException;

/**
 * Tool de escritura: crea un artículo del catálogo (US3).
 */
class CrearArticulo extends ToolAsistente
{
    public function nombre(): string
    {
        return 'crear_articulo';
    }

    public function descripcion(): string
    {
        return 'Crea un artículo del catálogo (producto o servicio) con su precio y tipo impositivo. Requiere confirmación del usuario.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'tipo' => ['type' => 'string', 'enum' => ['producto', 'servicio']],
                'nombre' => ['type' => 'string'],
                'sku' => ['type' => 'string'],
                'precio' => ['type' => 'number', 'description' => 'Precio unitario sin impuestos.'],
                'tipo_impositivo' => ['type' => 'number', 'description' => 'IVA/IGIC aplicable en % (p. ej. 21).'],
                'unidad' => ['type' => 'string'],
            ],
            'required' => ['tipo', 'nombre', 'precio', 'tipo_impositivo'],
        ];
    }

    public function permisoRequerido(): string
    {
        return 'ver-articulos';
    }

    public function esLectura(): bool
    {
        return false;
    }

    public function proponer(array $parametros): array
    {
        $tipo = $parametros['tipo'] ?? null;
        $nombre = trim((string) ($parametros['nombre'] ?? ''));

        if (! in_array($tipo, ['producto', 'servicio'], true) || $nombre === '') {
            throw ValidationException::withMessages(['articulo' => 'Faltan datos: tipo y nombre son obligatorios.']);
        }

        if (! is_numeric($parametros['precio'] ?? null) || ! is_numeric($parametros['tipo_impositivo'] ?? null)) {
            throw ValidationException::withMessages(['articulo' => 'Precio y tipo impositivo deben ser numéricos.']);
        }

        $normalizados = [
            'tipo' => $tipo,
            'nombre' => $nombre,
            'sku' => $parametros['sku'] ?? null,
            'precio' => (float) $parametros['precio'],
            'tipo_impositivo' => (float) $parametros['tipo_impositivo'],
            'unidad' => $parametros['unidad'] ?? null,
        ];

        $resumen = "Crear {$tipo} «{$nombre}» a {$normalizados['precio']} € (IVA {$normalizados['tipo_impositivo']}%)";

        return ['resumen' => $resumen, 'parametros' => $normalizados];
    }

    public function ejecutar(array $parametros): array
    {
        $articulo = Articulo::create([
            'tenant_id' => tenant()->id,
            'tipo' => TipoArticulo::from($parametros['tipo']),
            'nombre' => $parametros['nombre'],
            'sku' => $parametros['sku'] ?? null,
            'precio' => $parametros['precio'],
            'tipo_impositivo' => $parametros['tipo_impositivo'],
            'unidad' => $parametros['unidad'] ?? null,
            'gestion_stock' => false,
            'activo' => true,
        ]);

        return [
            'id' => $articulo->id,
            'mensaje' => 'Artículo creado correctamente.',
            'url' => route('articulos.index'),
            'descripcion' => "Creó el artículo «{$articulo->nombre}» (#{$articulo->id})",
        ];
    }
}
