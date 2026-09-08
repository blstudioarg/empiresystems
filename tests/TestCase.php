<?php

namespace Tests;

use App\Models\Tenant;
use App\Models\User;
use App\Support\MenuTenant;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Uri;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ningún test debe depender de una llamada de red real (p. ej. GeolocalizadorIp contra
        // ip-api.com): sin esto, un test que renderiza logs.index con IPs de factory al azar
        // termina pegándole a la API real (lento y flaky en CI). Cada test que sí necesite una
        // respuesta debe declararla explícitamente con Http::fake([...]).
        Http::preventStrayRequests();

        // MenuTenant memoiza `estructura()` por tenant_id en una propiedad estática (pensada para
        // durar un único request real, un proceso PHP por request). RefreshDatabase hace rollback
        // por test pero no resetea el autoincrement de SQLite, así que un tenant_id puede
        // reutilizarse entre tests: sin este reset, el segundo test leería la estructura cacheada
        // del tenant del test anterior. Arrancar "en frío" en cada test replica el proceso nuevo
        // real y evita ese arrastre.
        (new \ReflectionProperty(MenuTenant::class, 'memo'))->setValue(null, []);
    }

    /**
     * Host fijado para las peticiones de este test (007-super-admin-tenants: la resolución de
     * tenant se hace por Host HTTP). Se antepone a toda URI relativa en prepareUrlForRequest();
     * `withServerVariables(['HTTP_HOST' => ...])` no basta porque `url()` ya arma una URL
     * absoluta con el host de APP_URL antes de que el server array se aplique.
     */
    protected ?string $testHost = null;

    /**
     * Host de dominio resuelto para un tenant de test (007-super-admin-tenants: la resolución
     * de tenant ahora se hace por Host HTTP, no por el tenant_id del usuario autenticado).
     */
    protected function domainFor(Tenant $tenant): string
    {
        return $tenant->fresh()->domains()->firstOrFail()->domain;
    }

    /**
     * Fija el Host que usarán todas las peticiones siguientes de este test.
     */
    protected function actingOnDomain(string $host): static
    {
        $this->testHost = $host;

        return $this;
    }

    /**
     * Loguea al usuario indicado enviando la petición al Host que corresponde a su tenant
     * (o al dominio central si es super admin / no tiene tenant), tal como exige el gate
     * login<->dominio de SetTenantContext/LoginController (007-super-admin-tenants). Deja el
     * host fijado para las peticiones siguientes del test.
     */
    protected function loginAs(User $user, string $password = 'secret123'): TestResponse
    {
        $host = $user->tenant_id
            ? $this->domainFor($user->tenant()->first())
            : config('tenancy.central_domains')[0];

        $this->actingOnDomain($host);

        return $this->post('/login', [
            'email' => $user->email,
            'password' => $password,
        ]);
    }

    /**
     * @param  Uri|string  $uri
     */
    protected function prepareUrlForRequest($uri)
    {
        $uri = $uri instanceof Uri ? $uri->value() : $uri;

        if ($this->testHost && ! preg_match('#^https?://#i', $uri)) {
            $uri = 'http://'.$this->testHost.'/'.ltrim($uri, '/');
        }

        return parent::prepareUrlForRequest($uri);
    }
}
