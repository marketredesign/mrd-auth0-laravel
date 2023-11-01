<?php


namespace Marketredesign\MrdAuth0Laravel\Tests;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Marketredesign\MrdAuth0Laravel\Model\Stateful\User as StatefulUser;
use Marketredesign\MrdAuth0Laravel\Model\Stateless\User as StatelessUser;
use Marketredesign\MrdAuth0Laravel\MrdAuth0LaravelServiceProvider;

class TestCase extends \Orchestra\Testbench\TestCase
{
    /**
     * The maximum number of mocked responses a test case may use. Increase when not sufficient.
     */
    protected const RESPONSE_QUEUE_SIZE = 12;

    /**
     * @var array Fake responses that will be used by guzzle when using options from {@link createTestingGuzzleOptions}.
     */
    protected $mockedResponses;

    /**
     * @var array Container that will hold the guzzle history. Use this to verify the correct API requests were sent.
     */
    protected $guzzleContainer = [];

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

    /**
     * Create a mocked guzzle client such that we can intercept all API calls, fake the response, and inspect the call
     * that was made.
     * @return array Guzzle options array
     */
    protected function createTestingGuzzleOptions()
    {
        $responseQueue = [];

        // We need to provide the response queue before the user repository is created. However, this is before our test
        // method is executed, which should define the mocked responses. Thus, create a fixed number of closures before.
        for ($i = 0; $i < static::RESPONSE_QUEUE_SIZE; $i++) {
            // Wrap each response in a closure such that the responses can be defined at a later stage.
            $responseQueue[$i] = function () use ($i) {
                return $this->mockedResponses[$i];
            };
        }

        // Create handler stack with mock handler and history container.
        $mock = new MockHandler($responseQueue);
        $history = Middleware::history($this->guzzleContainer);
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push($history);

        return [
            'handler' => $handlerStack,
        ];
    }
}
