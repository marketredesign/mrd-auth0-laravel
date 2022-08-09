<?php


namespace Marketredesign\MrdAuth0Laravel\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Marketredesign\MrdAuth0Laravel\Contracts\Auth0Repository;
use Marketredesign\MrdAuth0Laravel\Facades\Auth0;
use Marketredesign\MrdAuth0Laravel\Tests\TestCase;

class Auth0FacadeTest extends TestCase
{
    /**
     * Verifies that the getMachineToMachineToken method is executed by our Auth0Repository implementation.
     */
    public function testGetMachineToMachineToken()
    {
        $this->mock(Auth0Repository::class, function ($mock) {
            $mock->shouldReceive('getMachineToMachineToken')->once()->withNoArgs();
        });

        Auth0::getMachineToMachineToken();
    }

    /**
     * Verifies that the Auth0 facade can be faked and returns some machine to machine token when queried.
     */
    public function testFake()
    {
        // Enable testing mode.
        Auth0::fake();

        // Verify a machine token can be retrieved in testing mode without overwriting any of the defaults.
        self::assertIsString(Auth0::getMachineToMachineToken());
    }

    /**
     * Verifies that a fake expires in value can be set in testing mode.
     */
    public function testFakeSetExpiresIn()
    {
        // Enable testing mode
        Auth0::fake();

        // Call function under test.
        Auth0::fakeSetM2mExpiresIn(500);

        // Mock cache remember and verify called with TTL 500/2 = 250.
        Cache::shouldReceive('remember')->withArgs(function ($key, $ttl) {
            return value($ttl) == 250;
        })->andReturn('unused');

        // Retrieve m2m function such that we can verify the cache call.
        Auth0::getMachineToMachineToken();
    }

    /**
     * Verifies that a fake access token can be set in testing mode.
     */
    public function testFakeSetAccessToken()
    {
        // Enable testing mode
        Auth0::fake();

        // Call function under test.
        Auth0::fakeSetM2mAccessToken('access_token_for_fake_set_access_token');

        // Verify token was indeed set.
        self::assertEquals('access_token_for_fake_set_access_token', Auth0::getMachineToMachineToken());
    }
}
