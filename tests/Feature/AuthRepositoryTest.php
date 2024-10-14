<?php

namespace Marketredesign\MrdAuth0Laravel\Tests\Feature;

use Carbon\Carbon;
use Exception;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Marketredesign\MrdAuth0Laravel\Contracts\AuthRepository;
use Marketredesign\MrdAuth0Laravel\Repository\Fakes\FakeAuthRepository;
use Marketredesign\MrdAuth0Laravel\Tests\TestCase;

class AuthRepositoryTest extends TestCase
{
    /** Repository under test */
    protected AuthRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = App::make(AuthRepository::class);
    }

    /**
     * Verifies that our implementation of the Auth0 Repository is bound in the service container, and that it can be
     * instantiated.
     */
    public function testServiceBinding()
    {
        // Verify it is indeed our instance.
        $this->assertInstanceOf(\Marketredesign\MrdAuth0Laravel\Repository\AuthRepository::class, $this->repo);
        $this->assertNotInstanceOf(FakeAuthRepository::class, $this->repo);
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
        // Call the function under test again and verify a string is returned now.
        self::assertIsString($this->repo->getMachineToMachineToken());
    }

    /**
     * Verifies that the get m2m token function calls the correct API endpoint and returns the expected value.
     */
    public function testGetM2mToken()
    {
        Http::fake([
            'https://domain.test/token' => Http::response([
                'expires_in' => 200,
                'access_token' => 'some_access_token',
            ]),
        ]);

        // Call the function under test.
        $token = $this->repo->getMachineToMachineToken();

        // Verify access token that was returned.
        self::assertEquals('some_access_token', $token);

        // Expect only 1 API call was made.
        Http::assertSentCount(1);

        // Verify correct url was called.
        Http::assertSent(function (Request $request) {
            return $request->url() == 'https://domain.test/token';
        });
    }

    /**
     * Verifies that the m2m tokens are cached for half their expiration time.
     */
    public function testGetM2mTokenCaching()
    {
        // Add some mocked responses, each with different access token.
        Http::fakeSequence('https://domain.test/token')
            ->push(['expires_in' => 200, 'access_token' => 'some_access_token'])
            ->push(['expires_in' => 683, 'access_token' => 'other_access_token'])
            ->push(['expires_in' => 0, 'access_token' => 'last_access_token']);

        // Call function under test twice and verify access token.
        self::assertEquals('some_access_token', $this->repo->getMachineToMachineToken());
        self::assertEquals('some_access_token', $this->repo->getMachineToMachineToken());

        // Verify only 1 api call was made.
        Http::assertSentCount(1);

        // Increment time such that cache TTL should not have passed yet.
        Carbon::setTestNow(Carbon::now()->addSeconds(98));

        // Call function under test again and verify access token still the same.
        self::assertEquals('some_access_token', $this->repo->getMachineToMachineToken());
        // Verify still only 1 api call was made.
        Http::assertSentCount(1);

        // Increment time such that cache TTL should have passed now. NB: it expires after half the time has passed.
        Carbon::setTestNow(Carbon::now()->addSeconds(101));

        // Call the function under test twice and expect the other access token is returned now.
        self::assertEquals('other_access_token', $this->repo->getMachineToMachineToken());
        self::assertEquals('other_access_token', $this->repo->getMachineToMachineToken());

        // Verify now 2 API calls have been made.
        Http::assertSentCount(2);

        // Now let's also verify an expires_in attribute that is odd.
        Carbon::setTestNow(Carbon::now()->addSeconds(340));
        self::assertEquals('other_access_token', $this->repo->getMachineToMachineToken());
        Carbon::setTestNow(Carbon::now()->addSeconds(342));
        self::assertEquals('last_access_token', $this->repo->getMachineToMachineToken());
        Http::assertSentCount(3);
    }
}
