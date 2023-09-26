<?php


namespace Marketredesign\MrdAuth0Laravel\Tests\Feature;

use Auth0\Laravel\Facade\Auth0;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Marketredesign\MrdAuth0Laravel\Contracts\Auth0Repository;
use Marketredesign\MrdAuth0Laravel\Repository\Fakes\FakeAuth0Repository;
use Marketredesign\MrdAuth0Laravel\Tests\TestCase;
use PsrMock\Psr18\Client;

class Auth0RepositoryTest extends TestCase
{
    /** Repository under test */
    protected Auth0Repository $repo;

    private Client $httpClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = App::make(Auth0Repository::class);

        // Set Auth0 config variables to some value such that the SDK can be instantiated.
        Config::set('auth0.domain', 'domain.test');
        Config::set('auth0.clientId', 'clientId.test');
        Config::set('auth0.clientSecret', 'clientSecret.test');

        // Let the Auth0 SDK use our mocked HTTP client.
        $this->httpClient = new Client();
        Config::set('auth0.httpClient', $this->httpClient);

        $this->resetAuth0Config();
    }

    /**
     * Verifies that our implementation of the Auth0 Repository is bound in the service container, and that it can be
     * instantiated.
     */
    public function testServiceBinding()
    {
        // Verify it is indeed our instance.
        $this->assertInstanceOf(\Marketredesign\MrdAuth0Laravel\Repository\Auth0Repository::class, $this->repo);
        $this->assertNotInstanceOf(FakeAuth0Repository::class, $this->repo);
    }

    /**
     * Verifies machine-to-machine token can only be retrieved when the application is running in console.
     */
    public function testM2mOnlyInConsole()
    {
        // Mock app to think it's not running in console.
        App::shouldReceive('runningInConsole')->andReturn(false);

        // Expect an exception to be thrown.
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Machine to machine tokens are only supposed to be used in CLI runs.');

        // Call the function under test.
        $this->repo->getMachineToMachineToken();

        // As a sanity check we will also verify that no exception is thrown when running in console.
        // As such, first set something in the cache so no actual tokens will be retrieved by the repo.
        Cache::forever('auth0-m2m-token', [
            'expires_in' => 10,
            'access_token' => 'unused',
        ]);

        // Mock the app to think it's running in console now.
        App::shouldReceive('runningInConsole')->andReturn(true);
        // Call the function uner test again and verify a string is returned now.
        self::assertIsString($this->repo->getMachineToMachineToken());
    }

    /**
     * Verifies that the get m2m token function calls the correct API endpoint and returns the expected value.
     */
    public function testGetM2mToken()
    {
        Auth0::getSdk()->authentication()->getHttpClient()
            ->mockResponse(new Response(200, [], '{"expires_in": 200, "access_token": "some_access_token"}'));

        // Call the function under test.
        $token = $this->repo->getMachineToMachineToken();

        // Verify access token that was returned.
        self::assertEquals('some_access_token', $token);

        // Expect only 1 API call was made.
        self::assertCount(1, $this->httpClient->getTimeline());

        // Find the request that was sent to "Auth0".
        $request = Auth0::getSdk()->authentication()->getHttpClient()->getLastRequest();
        // Verify correct url was called.
        self::assertEquals('oauth/token', $request->getUrl());
    }

    /**
     * Verifies that the m2m tokens are cached for half their expiration time.
     */
    public function testGetM2mTokenCaching()
    {
        // Add some mocked responses, each with different access token.
        Auth0::getSdk()->authentication()->getHttpClient()
            ->mockResponse(new Response(200, [], '{"expires_in": 200, "access_token": "some_access_token"}'))
            ->mockResponse(new Response(200, [], '{"expires_in": 683, "access_token": "other_access_token"}'))
            ->mockResponse(new Response(200, [], '{"expires_in": 0, "access_token": "last_access_token"}'));

        // Call function under test twice and verify access token.
        self::assertEquals('some_access_token', $this->repo->getMachineToMachineToken());
        self::assertEquals('some_access_token', $this->repo->getMachineToMachineToken());

        // Verify only 1 api call was made.
        self::assertCount(1, $this->httpClient->getTimeline());

        // Increment time such that cache TTL should not have passed yet.
        Carbon::setTestNow(Carbon::now()->addSeconds(98));

        // Call function under test again and verify access token still the same.
        self::assertEquals('some_access_token', $this->repo->getMachineToMachineToken());
        // Verify still only 1 api call was made.
        self::assertCount(1, $this->httpClient->getTimeline());

        // Increment time such that cache TTL should have passed now. NB: it expires after half the time has passed.
        Carbon::setTestNow(Carbon::now()->addSeconds(101));

        // Call the function under test twice and expect the other access token is returned now.
        self::assertEquals('other_access_token', $this->repo->getMachineToMachineToken());
        self::assertEquals('other_access_token', $this->repo->getMachineToMachineToken());

        // Verify now 2 API calls have been made.
        self::assertCount(2, $this->httpClient->getTimeline());

        // Now let's also verify an expires_in attribute that is odd.
        Carbon::setTestNow(Carbon::now()->addSeconds(340));
        self::assertEquals('other_access_token', $this->repo->getMachineToMachineToken());
        Carbon::setTestNow(Carbon::now()->addSeconds(342));
        self::assertEquals('last_access_token', $this->repo->getMachineToMachineToken());
    }
}
