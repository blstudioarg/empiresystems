<?php

namespace Tests\Unit;

use App\Enums\EntidadLogActividad;
use App\Excel\ColumnaExcel;
use App\Excel\Definiciones\DefinicionAlbaranes;
use App\Excel\Definiciones\DefinicionArticulos;
use App\Excel\Definiciones\DefinicionClientes;
use App\Excel\Definiciones\DefinicionFacturas;
use App\Excel\Definiciones\DefinicionLeads;
use App\Excel\Definiciones\DefinicionProveedores;
use App\Excel\DefinicionExcel;
use App\Excel\DefinicionExportable;
use App\Excel\DefinicionImportable;
use App\Excel\FormatoCelda;
use App\Excel\RegistroDefiniciones;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;

class DefinicionesExcelTest extends TestCase
{
    /**
     * @return DefinicionImportable[]
     */
    private function definicionesImportables(): array
    {
        return [
            new DefinicionClientes,
            new DefinicionArticulos,
            new DefinicionProveedores,
        ];
    }

    /**
     * SC-007 / FR-024: exportación, plantilla e importación leen la misma lista `columnas()`, así
     * que las cabeceras del fichero exportado/plantilla y las que busca el importador son
     * idénticas por construcción (misma `etiqueta`). Lo único que podría romper esa garantía es
     * una colisión: dos columnas cuya etiqueta normalice a la misma cabecera, donde la segunda
     * pisaría silenciosamente el valor leído de la primera al importar.
     */
    public function test_las_cabeceras_de_plantilla_export_e_import_coinciden(): void
    {
        foreach ($this->definicionesImportables() as $definicion) {
            $etiquetas = array_map(fn ($c) => $c->etiqueta, $definicion->columnas());
            $normalizadas = array_map(fn ($e) => ColumnaExcel::normalizarCabecera($e), $etiquetas);

            $this->assertSame(
                count($normalizadas),
                count(array_unique($normalizadas)),
                "En {$definicion->modulo()}, dos o más columnas normalizan a la misma cabecera."
            );

            // Cada clave interna debe estar declarada una sola vez (es la que usa validador()/crear()).
            $claves = array_map(fn ($c) => $c->clave, $definicion->columnas());
            $this->assertSame(count($claves), count(array_unique($claves)));
        }
    }

    /**
     * SC-010: registrar una definición nueva no debe requerir tocar ninguna de las existentes.
     */
    public function test_registrar_una_definicion_nueva_no_requiere_tocar_las_existentes(): void
    {
        $registro = new RegistroDefiniciones;

        $ficticia = new class implements DefinicionExportable
        {
            public function modulo(): string
            {
                return 'ficticio';
            }

            public function etiquetaModulo(): string
            {
                return 'ficticios';
            }

            public function permiso(): string
            {
                return 'ver-ficticio';
            }

            public function columnas(): array
            {
                return [
                    new ColumnaExcel('nombre', 'Nombre', true, FormatoCelda::Texto, fn (Model $m) => $m->nombre),
                ];
            }

            public function entidadLog(): EntidadLogActividad
            {
                return EntidadLogActividad::Cliente;
            }

            public function consultaExportacion(array $ids): Builder
            {
                throw new \RuntimeException('no usado en este test');
            }
        };

        $registro->registrar($ficticia);

        $this->assertSame($ficticia, $registro->resolverExportable('ficticio'));
        $this->assertSame($ficticia, $registro->resolver('ficticio'));

        // Las cinco definiciones reales siguen intactas y accesibles: registrar la ficticia no
        // pisó ni modificó nada.
        $this->assertInstanceOf(DefinicionExcel::class, $registro->resolver('clientes'));
        $this->assertInstanceOf(DefinicionExcel::class, $registro->resolver('articulos'));
        $this->assertInstanceOf(DefinicionExcel::class, $registro->resolver('facturas'));
        $this->assertInstanceOf(DefinicionExcel::class, $registro->resolver('albaranes'));
        $this->assertInstanceOf(DefinicionExcel::class, $registro->resolver('leads'));
        $this->assertInstanceOf(DefinicionExcel::class, $registro->resolver('proveedores'));
    }

    public function test_facturas_albaranes_y_leads_no_implementan_la_importable(): void
    {
        $this->assertNotInstanceOf(DefinicionImportable::class, new DefinicionFacturas);
        $this->assertNotInstanceOf(DefinicionImportable::class, new DefinicionAlbaranes);
        $this->assertNotInstanceOf(DefinicionImportable::class, new DefinicionLeads);
    }

    public function test_proveedores_no_implementa_la_exportable(): void
    {
        $this->assertNotInstanceOf(DefinicionExportable::class, new DefinicionProveedores);
    }
}
