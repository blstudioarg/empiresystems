<?php

namespace Tests;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
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
    protected function loginAs(User $user, string $password = 'secret123'): \Illuminate\Testing\TestResponse
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
     * @param  \Illuminate\Support\Uri|string  $uri
     */
    protected function prepareUrlForRequest($uri)
    {
        $uri = $uri instanceof \Illuminate\Support\Uri ? $uri->value() : $uri;

        if ($this->testHost && ! preg_match('#^https?://#i', $uri)) {
            $uri = 'http://'.$this->testHost.'/'.ltrim($uri, '/');
        }

        return parent::prepareUrlForRequest($uri);
    }
}
