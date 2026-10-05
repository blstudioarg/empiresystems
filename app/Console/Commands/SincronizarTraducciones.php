<?php

namespace App\Console\Commands;

use App\Exceptions\TraduccionNoDisponibleException;
use App\Traduccion\ExtractorClaves;
use App\Traduccion\MemoriaTraducciones;
use App\Traduccion\ProveedorTraduccion;
use Illuminate\Console\Command;

/**
 * Paso del deploy (feature 050, research D4): extrae los textos de un ámbito, registra los nuevos
 * como pendientes, sincroniza el glosario y traduce lo pendiente.
 *
 * **Nunca falla por la API** (caída, timeout, cupo agotado): lo informa en el resumen y deja los
 * textos pendientes para el siguiente intento o para el respaldo de primer uso. Solo devuelve error
 * si falta la clave y se pidió traducir. Idempotente: una segunda ejecución sin textos nuevos no
 * llama a la API (SC-009).
 */
class SincronizarTraducciones extends Command
{
    protected $signature = 'traducciones:sincronizar
        {--ambito=pos : Ámbito declarado en config/traduccion.php}
        {--idioma=zh : Idioma de destino}
        {--solo-extraer : Solo registrar los textos nuevos, sin llamar a la API}
        {--retraducir : Volver a traducir todo el ámbito (tras cambiar el glosario); no toca las correcciones}';

    protected $description = 'Extrae los textos traducibles de un ámbito y traduce los pendientes (DeepL, con glosario)';

    public function handle(ExtractorClaves $extractor, MemoriaTraducciones $memoria, ProveedorTraduccion $proveedor): int
    {
        $ambito = (string) $this->option('ambito');
        $idioma = (string) $this->option('idioma');

        if (! array_key_exists($idioma, (array) config('traduccion.idiomas')) || $idioma === config('traduccion.idioma_origen')) {
            $this->error("Idioma no traducible: {$idioma}");

            return self::FAILURE;
        }

        $claves = $extractor->extraer($ambito);
        $nuevas = $memoria->registrarPendientes($claves, $ambito, $idioma);

        $this->info(sprintf('Ámbito «%s»: %d textos encontrados, %d nuevos.', $ambito, count($claves), $nuevas));

        if ($this->option('retraducir') && ! $this->option('solo-extraer')) {
            $this->line(sprintf('%d textos marcados para volver a traducir.', $memoria->marcarParaRetraducir($ambito, $idioma)));
        }

        if ($this->option('solo-extraer')) {
            $this->line(sprintf('Pendientes de traducir (%s): %d.', $idioma, $memoria->contarPendientes($idioma)));

            return self::SUCCESS;
        }

        // Nada que traducir: ni glosario ni traducción, cero llamadas a la API (SC-009).
        if ($memoria->contarPorTraducir($idioma) === 0) {
            $this->info('No hay textos pendientes de traducir.');

            return self::SUCCESS;
        }

        if ((string) config('traduccion.deepl.api_key') === '') {
            $this->error('Falta DEEPL_API_KEY: no se puede traducir. Usa --solo-extraer o configura la clave.');

            return self::FAILURE;
        }

        try {
            $glosario = $proveedor->sincronizarGlosario($idioma, (array) config("traduccion.glosario.{$idioma}", []));
            $this->line('Glosario: '.($glosario ?? 'sin entradas'));
        } catch (TraduccionNoDisponibleException $e) {
            // Sin glosario se sigue traduciendo: peor calidad en los términos fijados, pero el
            // deploy no se bloquea. El siguiente intento lo vuelve a sincronizar.
            $this->warn('No se pudo sincronizar el glosario: '.$e->getMessage());
        }

        $resumen = $memoria->traducirPendientes($idioma, timeout: (int) config('traduccion.timeout_comando', 30));

        $this->info(sprintf(
            'Traducidas: %d · con error: %d · pendientes: %d · caracteres enviados: %d.',
            $resumen['traducidas'], $resumen['errores'], $resumen['pendientes'], $resumen['caracteres'],
        ));

        if ($resumen['fallo'] !== null) {
            $this->warn('El servicio de traducción falló: '.$resumen['fallo'].' Los textos quedan pendientes para el próximo intento.');
        }

        return self::SUCCESS;
    }
}
