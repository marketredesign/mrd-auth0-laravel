<?php


namespace Marketredesign\MrdAuth0Laravel\Tests;

use Auth0\Laravel\Auth\Guard;
use Auth0\Laravel\Auth\User\Repository;
use Auth0\Laravel\Contract\Auth\Guard as GuardContract;
use Auth0\Laravel\Entities\Credential;
use Auth0\Laravel\Facade\Auth0;
use Auth0\Laravel\Model\Imposter;
use Auth0\Laravel\ServiceProvider;
use Auth0\SDK\Exception\ConfigurationException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Support\Facades\Config;
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

    protected function getPackageProviders($app)
    {
        return [
            ServiceProvider::class,
            MrdAuth0LaravelServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('auth.defaults.guard', 'auth0');

        Config::set('auth.guards.auth0', [
            'driver' => 'auth0.guard',
            'provider' => 'auth0',
        ]);

        Config::set('auth.providers.auth0', [
            'driver' => 'auth0.provider',
            'repository' => Repository::class,
        ]);
    }

    /**
     * use this method to impersonate a specific auth0 user.
     * if you pass an attributes array, it will be merged with a set of default values
     *
     * @param array $attributes
     *
     * @return InteractsWithAuthentication
     */
    public function actingAsAuth0User(array $attributes = [])
    {
        $defaults = [
            'sub' => 'some-auth0-user-id',
            'azp' => 'some-auth0-appplication-client-id',
            'iat' => time(),
            'exp' => time() + 60 * 60,
            'scope' => '',
        ];

        $attributes = array_merge($defaults, $attributes);
        $instance = auth()->guard('auth0');
        $user = new Imposter($attributes);

        if (!($instance instanceof GuardContract)) {
            return $this->actingAs($user, 'auth0');
        }

        $credential = Credential::create(
            user: $user,
            accessTokenScope: $attributes['scope'] ? explode(' ', $attributes['scope']) : [],
        );

        $instance->setCredential($credential, Guard::SOURCE_IMPERSONATE);
        $instance->setImpersonating(true);

        return $this->actingAs($user, 'auth0');
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

    /**
     * Resets the config used by the Auth0 SDK that is used by the Auth0 Facade.
     * Should be called after updating the auth0 config.
     *
     * @return void
     * @throws ConfigurationException
     */
    protected function resetAuth0Config(): void
    {
        Auth0::reset();
    }
}
