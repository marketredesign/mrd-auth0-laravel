<?php


namespace Marketredesign\MrdAuth0Laravel\Tests;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Marketredesign\MrdAuth0Laravel\Model\Stateful\User as StatefulUser;
use Marketredesign\MrdAuth0Laravel\Model\Stateless\User as StatelessUser;
use Marketredesign\MrdAuth0Laravel\MrdAuth0LaravelServiceProvider;
use Marketredesign\MrdAuth0Laravel\Traits\ActingAsPricecypherUser;

class TestCase extends \Orchestra\Testbench\TestCase
{
    use OidcTestingValues, ActingAsPricecypherUser;

    protected string $guard = 'pc-jwt';

    protected function getPackageProviders($app)
    {
        return [
            MrdAuth0LaravelServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->oidcTestingInit();

        // Let the OIDC SDK use our mocked HTTP client.
        Config::set('pricecypher-oidc.http_client', new HttpPsrClientBridge());
        Config::set('pricecypher-oidc.issuer', $this->oidcIssuer);
        Config::set('pricecypher-oidc.client_id', 'id');
        Config::set('pricecypher-oidc.client_secret', 'secret');

        $openidConfig = $this->openidConfig();
        Http::fake([
            $this->oidcIssuerUrl('/.well-known/openid-configuration') => Http::response($openidConfig),
            $openidConfig['jwks_uri'] => Http::response($this->openidJwksConfig()),
        ]);


        Config::set('auth.guards.pc-jwt', [
            'driver' => 'pc-jwt',
            'provider' => 'pc-users',
        ]);
        Config::set('auth.guards.pc-oidc', [
            'driver' => 'pc-oidc',
            'provider' => 'pc-users',
        ]);

        Config::set('auth.providers.pc-users', [
            'driver' => 'pc-users',
        ]);

        Config::set('auth.defaults.guard', $this->guard);
    }

    /**
     * Authorise / authenticate a request.
     * if you pass an attributes array, it will be merged with a set of default values
     * TODO
     */
    public function auth(array $attributes = [], bool $stateless = null): TestCase
    {
        $isStateless = $stateless ?? ($this->guard !== 'pc-oidc');
        return $this->actingAsPricecypherUser($attributes, $isStateless);
    }
}
