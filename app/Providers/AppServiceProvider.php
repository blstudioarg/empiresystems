<?php

namespace App\Providers;

use App\Excel\RegistroDefiniciones;
use App\Models\User;
use App\Support\ConfigTenant;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->desactivarDeteccionMimePorFinfo();

        // Illuminate\Auth\Events\{Login,Logout,Failed,Lockout} -> LogAuthenticationActivity ya
        // quedan enganchados por el auto-discovery de eventos de Laravel (los métodos handle*
        // están type-hinted con la clase del evento): registrarlos aquí también los disparaba
        // dos veces por request (detectado al añadir el registro en logs_actividad — feature 021).

        // El super admin central (sin tenant, sin roles spatie) pasa cualquier check de permisos
        // (feature 027, research.md D4). Devuelve null para no cortocircuitar el resto de checks
        // del resto de usuarios; las rutas super_admin.* mantienen además EnsureSuperAdmin.
        Gate::before(fn (User $user) => $user->isSuperAdmin() ? true : null);

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
                new ExtensionMimeTypeDetector(),
            );

            return new FilesystemAdapter(new Flysystem($adapter, $config), $adapter, $config);
        });
    }
}
