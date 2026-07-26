<?php

namespace App\Excel;

use App\Excel\Definiciones\DefinicionAlbaranes;
use App\Excel\Definiciones\DefinicionArticulos;
use App\Excel\Definiciones\DefinicionClientes;
use App\Excel\Definiciones\DefinicionFacturas;
use App\Excel\Definiciones\DefinicionLeads;
use App\Excel\Definiciones\DefinicionLogsActividad;
use App\Excel\Definiciones\DefinicionProveedores;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Resuelve un módulo (string de la URL) a su {@see DefinicionExcel}. Registrado como singleton
 * (`AppServiceProvider`). Añadir un módulo nuevo es una clase de definición nueva más una llamada
 * a {@see registrar()} — nunca obliga a tocar las definiciones ya existentes (SC-010).
 */
class RegistroDefiniciones
{
    /** @var array<string, DefinicionExcel> */
    private array $definiciones = [];

    public function __construct()
    {
        $this->registrar(new DefinicionClientes);
        $this->registrar(new DefinicionArticulos);
        $this->registrar(new DefinicionProveedores);
        $this->registrar(new DefinicionFacturas);
        $this->registrar(new DefinicionAlbaranes);
        $this->registrar(new DefinicionLeads);
        $this->registrar(new DefinicionLogsActividad);
    }

    public function registrar(DefinicionExcel $definicion): void
    {
        $this->definiciones[$definicion->modulo()] = $definicion;
    }

    public function resolver(string $modulo): DefinicionExcel
    {
        return $this->definiciones[$modulo] ?? throw new NotFoundHttpException("Módulo desconocido: {$modulo}.");
    }

    /**
     * @throws NotFoundHttpException si el módulo no existe o no implementa la exportación.
     */
    public function resolverExportable(string $modulo): DefinicionExportable
    {
        $definicion = $this->resolver($modulo);

        if (! $definicion instanceof DefinicionExportable) {
            throw new NotFoundHttpException("El módulo [{$modulo}] no admite exportación.");
        }

        return $definicion;
    }

    /**
     * @throws NotFoundHttpException si el módulo no existe o no implementa la importación.
     */
    public function resolverImportable(string $modulo): DefinicionImportable
    {
        $definicion = $this->resolver($modulo);

        if (! $definicion instanceof DefinicionImportable) {
            throw new NotFoundHttpException("El módulo [{$modulo}] no admite importación.");
        }

        return $definicion;
    }
}
