<?php


namespace Marketredesign\MrdAuth0Laravel\Tests;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Marketredesign\MrdAuth0Laravel\Model\Stateful\User as StatefulUser;
use Marketredesign\MrdAuth0Laravel\Model\Stateless\User as StatelessUser;
use Marketredesign\MrdAuth0Laravel\MrdAuth0LaravelServiceProvider;

class TestCase extends \Orchestra\Testbench\TestCase
{
    protected $guard = 'pc-jwt';

    protected function getPackageProviders($app)
    {
        return [
            MrdAuth0LaravelServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Let the OIDC SDK use our mocked HTTP client.
        Config::set('pricecypher-oidc.http_client', new HttpPsrClientBridge());
        Config::set('pricecypher-oidc.issuer', 'https://domain.test');
        Config::set('pricecypher-oidc.client_id', 'id');
        Config::set('pricecypher-oidc.client_secret', 'secret');

        Http::fake([
            'https://domain.test/.well-known/openid-configuration' => Http::response([
                'issuer' => 'https://domain.test',
                'authorization_endpoint' => 'https://domain.test/authorize',
                "token_endpoint" => "https://domain.test/token",
                'jwks_uri' => 'https://domain.test/jwks',
            ]),
            'https://domain.test/jwks' => Http::response([
                "keys" => [
                    [
                        "kid" => "kid1",
                        "kty" => "RSA",
                        "alg" => "RS256",
                        "use" => "sig",
                        "n" => "verybign",
                        "e" => "AQAB",
                        "x5c" => ["certcontents"],
                        "x5t" => "PJval",
                        "x5t#S256" => "e9val",
                    ],
                ],
            ])
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
     * @param bool $stateless
     * @return TestCase
     */
    public function auth(array $attributes = [], bool $stateless = true): TestCase
    {
        $defaults = [
            'sub' => 'some-auth0-user-id',
            'azp' => 'some-auth0-appplication-client-id',
            'iat' => time(),
            'exp' => time() + 60 * 60,
            'scope' => '',
        ];

        $attributes = array_merge($defaults, $attributes);
        $user = $stateless ? new StatelessUser($attributes) : new StatefulUser($attributes);

        return $this->actingAs($user, $this->guard);
    }
}
