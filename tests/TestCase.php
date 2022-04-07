<?php


namespace Marketredesign\MrdAuth0Laravel\Tests;

use Auth0\Login\Auth0Service;
use Auth0\SDK\API\Authentication;
use Auth0\SDK\Exception\InvalidTokenException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Illuminate\Support\Arr;
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

    protected $userId = 'test';

    protected function getPackageProviders($app)
    {
        return [
            MrdAuth0LaravelServiceProvider::class,
        ];
    }

    /**
     * Mock the Auth0Service. When the provided {@code jwt} is null, the JWT decoding is mocked to
     * throw an {@code InvalidTokenException}. Otherwise, it is mocked to return the provided JWT. When the provided
     * JWT does not include a `sub` field, it is added and set to {@code $this->userId}. The userinfo method is
     * mocked to return the provided {@code $userInfo} array.
     *
     * @param array|null $jwt Mocked "decoded" JWT.
     * @param array|null $userInfo Mocked userinfo.
     * @return TestCase this
     */
    protected function mockAuth0Service(?array $jwt, ?array $userInfo = null)
    {
        $this->mock(Auth0Service::class, function ($mock) use ($jwt) {
            $decodeJwt = $mock->shouldReceive('decodeJWT');

            if ($jwt === null) {
                $decodeJwt->andThrow(InvalidTokenException::class);
            } else {
                // Add sub to the JWT if it's not already defined.
                $jwt = Arr::add($jwt, 'sub', $this->userId);
                $decodeJwt->andReturn($jwt);
            }
        });

        // Mock the userinfo method in the Authentication SDK such that it does not call Auth0's API.
        $this->mock(Authentication::class, function ($mock) use ($userInfo) {
            $mock->shouldReceive('userinfo')->andReturn($userInfo);
        });

        return $this;
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
