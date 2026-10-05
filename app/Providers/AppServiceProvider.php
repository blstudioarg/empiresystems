<?php

namespace App\Providers;

use App\Excel\RegistroDefiniciones;
use App\Models\User;
use App\Support\ConfigTenant;
use App\Traduccion\CargadorTraducciones;
use App\Traduccion\MemoriaTraducciones;
use App\Traduccion\ProveedorTraduccion;
use App\Traduccion\TraductorDeepl;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use League\Flysystem\Visibility;
use League\MimeTypeDetection\ExtensionMimeTypeDetector;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(RegistroDefiniciones::class);

        // Traducción de la interfaz (feature 050, research D2): el loader de Laravel pasa a servir
        // también las traducciones guardadas en base de datos para los idiomas traducidos.
        $this->app->extend('translation.loader', fn ($loader) => new CargadorTraducciones($loader));
        $this->app->bind(ProveedorTraduccion::class, TraductorDeepl::class);
        $this->app->singleton(MemoriaTraducciones::class);

        require_once app_path('Traduccion/helpers.php');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->desactivarDeteccionMimePorFinfo();

        $this->registrarAssetVersionado();

        $this->registrarRespaldoTraducciones();

        // Illuminate\Auth\Events\{Login,Logout,Failed,Lockout} -> LogAuthenticationActivity ya
        // quedan enganchados por el auto-discovery de eventos de Laravel (los métodos handle*
        // están type-hinted con la clase del evento): registrarlos aquí también los disparaba
        // dos veces por request (detectado al añadir el registro en logs_actividad — feature 021).

        // El super admin central (sin tenant, sin roles spatie) pasa cualquier check de permisos
        // (feature 027, research.md D4). Devuelve null para no cortocircuitar el resto de checks
        // del resto de usuarios; las rutas super_admin.* mantienen además EnsureSuperAdmin.
        Gate::before(fn (User $user) => $user->isSuperAdmin() ? true : null);

        // Abrir la caja (feature 048): quien gestiona la caja, o quien cobra en el TPV — un cajero
        // tiene que poder abrirla desde la pantalla de venta sin acceso al resto de la caja.
        Gate::define('abrir-caja', fn (User $user) => $user->can('ver-pos-caja') || $user->can('ver-pos-crear'));

        // Convierte un datetime (guardado en UTC) a la zona horaria del tenant activo, solo para
        // mostrarlo. Azúcar para vistas/JSON dentro de contexto de tenant: `$fecha->enZonaTenant()`.
        // Sin tenant activo (contexto central) cae al default. Solo para datetimes reales, no para
        // campos `date` puros (ver ConfigTenant::paraMostrar).
        Carbon::macro('enZonaTenant', function () {
            /** @var Carbon $this */
            $tenant = function_exists('tenant') ? tenant() : null;
            $zona = $tenant
                ? ConfigTenant::zonaHoraria($tenant->getTenantKey())
                : ConfigTenant::DEFAULT_ZONA_HORARIA;

            return $this->copy()->setTimezone($zona);
        });
    }

    /**
     * Los discos 'local', 'public' y 'documentos' (config/filesystems.php) usan driver "local",
     * cuyo adapter de Flysystem detecta el MIME con `finfo` por defecto (ext-fileinfo de PHP). En
     * hostings compartidos esa extensión puede faltar y no ser algo que podamos activar nosotros
     * (comprobado en despliegue a empiresass.gestionley.com, 2026-08: ni PHP 8.2 ni 8.3 la traían
     * instaladas ahí, y la cuenta cPanel no expone forma de activarla sin soporte del hosting).
     * Se reemplaza por ExtensionMimeTypeDetector, que resuelve el MIME por extensión contra la
     * tabla IANA embebida en league/mime-type-detection, sin depender de ninguna extensión de PHP.
     * No afecta a la validación de subida (StoreArchivoRequest usa Symfony Mime, otro mecanismo).
     */
    /**
     * `@assetv('js/x.js')` — como `asset()` pero con `?v=<mtime>` pegado detrás.
     *
     * Los JS y CSS propios se sirven sin versionar, así que el navegador se queda la copia vieja
     * después de cada despliegue y hay que explicarle al usuario que pulse Ctrl+F5. Con el mtime
     * del archivo en la URL, cada despliegue invalida **solo** lo que cambió: lo que no se tocó
     * sigue cacheado.
     *
     * Es una directiva de Blade y no una función global a propósito: una función global habría
     * que registrarla en el `files` del autoload de Composer, y el despliegue a este hosting es
     * por FTP —no corre `composer install`—, así que el autoload del servidor no se enteraría y
     * reventaría toda la app. Una directiva se compila dentro de la propia vista.
     *
     * `is_file` de guarda: si el archivo no está en disco (build a medias, ruta mal escrita) se
     * devuelve la URL sin versionar en vez de romper la página con un warning de `filemtime`.
     */
    private function registrarAssetVersionado(): void
    {
        Blade::directive('assetv', function (string $expression) {
            return "<?php \$__ruta = {$expression}; \$__abs = public_path(\$__ruta); ".
                "echo e(asset(\$__ruta).(is_file(\$__abs) ? '?v='.filemtime(\$__abs) : '')); ?>";
        });
    }

    /**
     * Respaldo «al primer uso» de la traducción (feature 050, research D4). Si en un request
     * traducido `__()` no encuentra un texto, se apunta; **después de enviar la respuesta**
     * (`terminating`, tras `fastcgi_finish_request` en PHP-FPM) se registra como pendiente y se
     * traduce con un timeout corto. El usuario lo ve en español esa vez y en chino desde la
     * siguiente, sin esperar nunca a la API (SC-003). Nada de esto puede romper el request.
     *
     * Las claves con forma de clave de archivo (`validation.required`, `auth.failed`) no son
     * textos en español y se ignoran.
     */
    private function registrarRespaldoTraducciones(): void
    {
        Lang::handleMissingKeysUsing(function (string $clave, array $replace, ?string $locale) {
            if (CargadorTraducciones::esIdiomaTraducido($locale) && ! preg_match('/^[a-z0-9_\-]+(\.[a-z0-9_\-]+)+$/', $clave)) {
                $this->app->make(MemoriaTraducciones::class)->apuntarAusente($clave, $locale);
            }

            return $clave;
        });

        $this->app->terminating(function () {
            try {
                $this->app->make(MemoriaTraducciones::class)->procesarAusentes();
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }

    private function desactivarDeteccionMimePorFinfo(): void
    {
        Storage::extend('local', function ($app, array $config) {
            $visibility = PortableVisibilityConverter::fromArray(
                $config['permissions'] ?? [],
                $config['directory_visibility'] ?? $config['visibility'] ?? Visibility::PRIVATE
            );

            $adapter = new LocalFilesystemAdapter(
                $config['root'],
                $visibility,
                $config['lock'] ?? LOCK_EX,
                LocalFilesystemAdapter::DISALLOW_LINKS,
                new ExtensionMimeTypeDetector,
            );

            return new FilesystemAdapter(new Flysystem($adapter, $config), $adapter, $config);
        });
    }
}
