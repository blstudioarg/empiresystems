<?php

namespace App\Http\Requests\Concerns;

use App\Support\DenominacionesEuro;
use Closure;

/**
 * Validación compartida del conteo por denominaciones de la caja (apertura y cierre, feature 048):
 * claves = céntimos del catálogo de {@see DenominacionesEuro}, valores = enteros 0–9.999.
 */
trait ValidaConteoCaja
{
    /** @return list<mixed> */
    protected function reglaConteo(): array
    {
        return [
            'array',
            function (string $atributo, mixed $valor, Closure $fallo) {
                if (! is_array($valor)) {
                    return;
                }

                foreach ($valor as $centimos => $cantidad) {
                    if (! is_numeric($centimos) || ! DenominacionesEuro::esValida((int) $centimos)) {
                        $fallo('El conteo incluye un billete o moneda que no existe.');

                        return;
                    }

                    if (filter_var($cantidad, FILTER_VALIDATE_INT) === false
                        || (int) $cantidad < 0 || (int) $cantidad > DenominacionesEuro::MAX_CANTIDAD) {
                        $fallo('Cada cantidad del conteo debe ser un número entero entre 0 y '.DenominacionesEuro::MAX_CANTIDAD.'.');

                        return;
                    }
                }
            },
        ];
    }
}
