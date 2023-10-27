<?php


namespace Marketredesign\MrdAuth0Laravel\Tests;

use Auth0\Laravel\Auth\User\Repository;
use Auth0\Laravel\Facade\Auth0;
use Auth0\Laravel\ServiceProvider;
use Auth0\SDK\Exception\ConfigurationException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Illuminate\Support\Facades\Config;
use Marketredesign\MrdAuth0Laravel\MrdAuth0LaravelServiceProvider;
use Marketredesign\MrdAuth0Laravel\Traits\ActingAsAuth0User;

class TestCase extends \Orchestra\Testbench\TestCase
{
    use ActingAsAuth0User;

    /**
     * The maximum number of mocked responses a test case may use. Increase when not sufficient.
     */
    protected const RESPONSE_QUEUE_SIZE = 12;

    /**
     * @var array Fake responses that will be used by guzzle when using options from {@link createTestingGuzzleOptions}.
     */
    protected array $mockedResponses;

    /**
     * @var array Container that will hold the guzzle history. Use this to verify the correct API requests were sent.
     */
    protected array $guzzleContainer = [];

    /**
     * @var string TODO
     */
    protected string $authGuard = 'auth0';

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

        Config::set('auth.defaults.guard', $this->authGuard);

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
