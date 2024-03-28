<?php


namespace Marketredesign\MrdAuth0Laravel\Tests;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Marketredesign\MrdAuth0Laravel\Model\Stateful\User as StatefulUser;
use Marketredesign\MrdAuth0Laravel\Model\Stateless\User as StatelessUser;
use Marketredesign\MrdAuth0Laravel\MrdAuth0LaravelServiceProvider;

class TestCase extends \Orchestra\Testbench\TestCase
{
    use OidcTestingValues;

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
     *
     * @param array $attributes
     * @return TestCase
     */
    public function auth(array $attributes = []): TestCase
    {
        $defaults = [
            'sub' => 'some-auth0-user-id',
            'azp' => 'some-auth0-appplication-client-id',
            'iat' => time(),
            'exp' => time() + 60 * 60,
            'scope' => '',
        ];

        $attributes = array_merge($defaults, $attributes);
        $user = $this->guard === 'pc-oidc' ? new StatefulUser($attributes) : new StatelessUser($attributes);

        return $this->actingAs($user, $this->guard);
    }
}
