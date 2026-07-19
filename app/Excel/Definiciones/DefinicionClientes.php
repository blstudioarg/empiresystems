<?php

namespace App\Excel\Definiciones;

use App\Enums\EntidadLogActividad;
use App\Enums\TipoCliente;
use App\Excel\ColumnaExcel;
use App\Excel\Concerns\NormalizaValores;
use App\Excel\Concerns\OrdenaPorIds;
use App\Excel\DefinicionExportable;
use App\Excel\DefinicionImportable;
use App\Excel\FormatoCelda;
use App\Http\Requests\StoreClienteRequest;
use App\Models\Cliente;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

/**
 * Clientes: exportable + importable (data-model.md §3.1).
 */
class DefinicionClientes implements DefinicionExportable, DefinicionImportable
{
    use NormalizaValores, OrdenaPorIds;

    public function modulo(): string
    {
        return 'clientes';
    }

    public function etiquetaModulo(): string
    {
        return 'clientes';
    }

    public function permiso(): string
    {
        return 'ver-clientes';
    }

    public function entidadLog(): EntidadLogActividad
    {
        return EntidadLogActividad::Cliente;
    }

    public function columnas(): array
    {
        return [
            new ColumnaExcel(
                clave: 'tipo',
                etiqueta: 'Tipo',
                obligatoria: true,
                formato: FormatoCelda::Texto,
                exportar: fn (Cliente $c) => $c->tipo === TipoCliente::Empresa ? 'Empresa' : 'Particular',
                importar: function (mixed $valor): string {
                    $texto = mb_strtolower(trim((string) ($valor ?? '')));

                    return $texto === 'empresa' ? TipoCliente::Empresa->value : TipoCliente::Particular->value;
                },
                ejemplo: 'Empresa',
            ),
            new ColumnaExcel(
                clave: 'nombre',
                etiqueta: 'Nombre',
                obligatoria: true,
                formato: FormatoCelda::Texto,
                exportar: fn (Cliente $c) => $c->nombre,
                importar: fn (mixed $v) => trim((string) ($v ?? '')),
                ejemplo: 'Acme SL',
            ),
            new ColumnaExcel(
                clave: 'razon_social',
                etiqueta: 'Razón social',
                obligatoria: false,
                formato: FormatoCelda::Texto,
                exportar: fn (Cliente $c) => $c->razon_social,
                importar: fn (mixed $v) => self::texto($v),
                ejemplo: 'Acme Sociedad Limitada',
            ),
            new ColumnaExcel(
                clave: 'nif',
                etiqueta: 'NIF',
                obligatoria: false,
                formato: FormatoCelda::Texto,
                exportar: fn (Cliente $c) => $c->nif,
                importar: fn (mixed $v) => self::texto($v),
                ejemplo: 'B12345674',
            ),
            new ColumnaExcel(
                clave: 'direccion',
                etiqueta: 'Dirección',
                obligatoria: false,
                formato: FormatoCelda::Texto,
                exportar: fn (Cliente $c) => $c->direccion,
                importar: fn (mixed $v) => self::texto($v),
                ejemplo: 'Calle Mayor 1',
            ),
            new ColumnaExcel(
                clave: 'cp',
                etiqueta: 'CP',
                obligatoria: false,
                formato: FormatoCelda::Texto,
                exportar: fn (Cliente $c) => $c->cp,
                importar: fn (mixed $v) => self::texto($v),
                ejemplo: '28001',
            ),
            new ColumnaExcel(
                clave: 'ciudad',
                etiqueta: 'Ciudad',
                obligatoria: false,
                formato: FormatoCelda::Texto,
                exportar: fn (Cliente $c) => $c->ciudad,
                importar: fn (mixed $v) => self::texto($v),
                ejemplo: 'Madrid',
            ),
            new ColumnaExcel(
                clave: 'provincia',
                etiqueta: 'Provincia',
                obligatoria: false,
                formato: FormatoCelda::Texto,
                exportar: fn (Cliente $c) => $c->provincia,
                importar: fn (mixed $v) => self::texto($v),
                ejemplo: 'Madrid',
            ),
            new ColumnaExcel(
                clave: 'pais',
                etiqueta: 'País',
                obligatoria: true,
                formato: FormatoCelda::Texto,
                exportar: fn (Cliente $c) => $c->pais,
                importar: fn (mixed $v) => self::texto($v) ? mb_strtoupper(trim((string) $v)) : 'ES',
                ejemplo: 'ES',
            ),
            new ColumnaExcel(
                clave: 'email',
                etiqueta: 'Email',
                obligatoria: false,
                formato: FormatoCelda::Texto,
                exportar: fn (Cliente $c) => $c->email,
                importar: fn (mixed $v) => self::texto($v),
                ejemplo: 'contacto@acme.es',
            ),
            new ColumnaExcel(
                clave: 'telefono',
                etiqueta: 'Teléfono',
                obligatoria: false,
                formato: FormatoCelda::Texto,
                exportar: fn (Cliente $c) => $c->telefono,
                importar: fn (mixed $v) => self::texto($v),
                ejemplo: '600123456',
            ),
            new ColumnaExcel(
                clave: 'aplica_recargo_equivalencia',
                etiqueta: 'Recargo de equivalencia',
                obligatoria: false,
                formato: FormatoCelda::Booleano,
                exportar: fn (Cliente $c) => $c->aplica_recargo_equivalencia ? 'Sí' : 'No',
                importar: fn (mixed $v) => self::booleano($v),
                ejemplo: 'No',
            ),
            new ColumnaExcel(
                clave: 'notas',
                etiqueta: 'Notas',
                obligatoria: false,
                formato: FormatoCelda::Texto,
                exportar: fn (Cliente $c) => $c->notas,
                importar: fn (mixed $v) => self::texto($v),
                ejemplo: null,
            ),
        ];
    }

    public function consultaExportacion(array $ids): Builder
    {
        return $this->ordenarPorIds(Cliente::whereIn('id', $ids), $ids);
    }

    public function validador(array $fila): ValidatorContract
    {
        $request = StoreClienteRequest::create('/', 'POST', $fila);

        return Validator::make($fila, $request->rules(), $request->messages(), $request->attributes());
    }

    public function crear(array $datos, int $tenantId): Model
    {
        return Cliente::create([...$datos, 'tenant_id' => $tenantId]);
    }

    public function camposUnicos(): array
    {
        return ['nif'];
    }
}
