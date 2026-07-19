<?php

namespace App\Excel\Definiciones;

use App\Enums\EntidadLogActividad;
use App\Excel\ColumnaExcel;
use App\Excel\Concerns\NormalizaValores;
use App\Excel\DefinicionExcel;
use App\Excel\DefinicionExportable;
use App\Excel\DefinicionImportable;
use App\Excel\FormatoCelda;
use App\Http\Requests\StoreProveedorRequest;
use App\Models\Proveedor;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

/**
 * Proveedores: solo importable, sin exportación en el alcance (data-model.md §3.3 — asimetría
 * deliberada, el usuario acotó la exportación a los cinco listados principales). Por eso
 * implementa {@see DefinicionExcel} y {@see DefinicionImportable} pero no
 * {@see DefinicionExportable}.
 */
class DefinicionProveedores implements DefinicionExcel, DefinicionImportable
{
    use NormalizaValores;

    public function modulo(): string
    {
        return 'proveedores';
    }

    public function etiquetaModulo(): string
    {
        return 'proveedores';
    }

    public function permiso(): string
    {
        return 'ver-proveedores';
    }

    public function entidadLog(): EntidadLogActividad
    {
        return EntidadLogActividad::Proveedor;
    }

    public function columnas(): array
    {
        return [
            new ColumnaExcel(
                clave: 'nombre',
                etiqueta: 'Nombre',
                obligatoria: false,
                formato: FormatoCelda::Texto,
                exportar: fn (Proveedor $p) => $p->nombre,
                importar: fn (mixed $v) => self::texto($v),
                ejemplo: 'Suministros Ibéricos SL',
            ),
            new ColumnaExcel(
                clave: 'razon_social',
                etiqueta: 'Razón social',
                obligatoria: false,
                formato: FormatoCelda::Texto,
                exportar: fn (Proveedor $p) => $p->razon_social,
                importar: fn (mixed $v) => self::texto($v),
                ejemplo: 'Suministros Ibéricos Sociedad Limitada',
            ),
            new ColumnaExcel(
                clave: 'nif',
                etiqueta: 'NIF',
                obligatoria: false,
                formato: FormatoCelda::Texto,
                exportar: fn (Proveedor $p) => $p->nif,
                importar: fn (mixed $v) => self::texto($v),
                ejemplo: 'B87654321',
            ),
            new ColumnaExcel(
                clave: 'direccion',
                etiqueta: 'Dirección',
                obligatoria: false,
                formato: FormatoCelda::Texto,
                exportar: fn (Proveedor $p) => $p->direccion,
                importar: fn (mixed $v) => self::texto($v),
                ejemplo: 'Polígono Industrial Norte, nave 4',
            ),
            new ColumnaExcel(
                clave: 'cp',
                etiqueta: 'CP',
                obligatoria: false,
                formato: FormatoCelda::Texto,
                exportar: fn (Proveedor $p) => $p->cp,
                importar: fn (mixed $v) => self::texto($v),
                ejemplo: '08001',
            ),
            new ColumnaExcel(
                clave: 'ciudad',
                etiqueta: 'Ciudad',
                obligatoria: false,
                formato: FormatoCelda::Texto,
                exportar: fn (Proveedor $p) => $p->ciudad,
                importar: fn (mixed $v) => self::texto($v),
                ejemplo: 'Barcelona',
            ),
            new ColumnaExcel(
                clave: 'provincia',
                etiqueta: 'Provincia',
                obligatoria: false,
                formato: FormatoCelda::Texto,
                exportar: fn (Proveedor $p) => $p->provincia,
                importar: fn (mixed $v) => self::texto($v),
                ejemplo: 'Barcelona',
            ),
            new ColumnaExcel(
                clave: 'pais',
                etiqueta: 'País',
                obligatoria: true,
                formato: FormatoCelda::Texto,
                exportar: fn (Proveedor $p) => $p->pais,
                importar: fn (mixed $v) => self::texto($v) ? mb_strtoupper(trim((string) $v)) : 'ES',
                ejemplo: 'ES',
            ),
            new ColumnaExcel(
                clave: 'email',
                etiqueta: 'Email',
                obligatoria: false,
                formato: FormatoCelda::Texto,
                exportar: fn (Proveedor $p) => $p->email,
                importar: fn (mixed $v) => self::texto($v),
                ejemplo: 'compras@suministrosibericos.es',
            ),
            new ColumnaExcel(
                clave: 'telefono',
                etiqueta: 'Teléfono',
                obligatoria: false,
                formato: FormatoCelda::Texto,
                exportar: fn (Proveedor $p) => $p->telefono,
                importar: fn (mixed $v) => self::texto($v),
                ejemplo: '930123456',
            ),
            new ColumnaExcel(
                clave: 'notas',
                etiqueta: 'Notas',
                obligatoria: false,
                formato: FormatoCelda::Texto,
                exportar: fn (Proveedor $p) => $p->notas,
                importar: fn (mixed $v) => self::texto($v),
                ejemplo: null,
            ),
        ];
    }

    public function validador(array $fila): ValidatorContract
    {
        $request = StoreProveedorRequest::create('/', 'POST', $fila);

        return Validator::make($fila, $request->rules());
    }

    public function crear(array $datos, int $tenantId): Model
    {
        return Proveedor::create([...$datos, 'tenant_id' => $tenantId]);
    }

    public function camposUnicos(): array
    {
        return ['nif'];
    }
}
