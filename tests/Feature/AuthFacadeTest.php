<?php

namespace Marketredesign\MrdAuth0Laravel\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Marketredesign\MrdAuth0Laravel\Contracts\AuthRepository;
use Marketredesign\MrdAuth0Laravel\Facades\PricecypherAuth;
use Marketredesign\MrdAuth0Laravel\Tests\TestCase;

class AuthFacadeTest extends TestCase
{
    /**
     * Verifies that the getMachineToMachineToken method is executed by our Auth0Repository implementation.
     */
    public function testGetMachineToMachineToken()
    {
        $this->mock(AuthRepository::class, function ($mock) {
            $mock->shouldReceive('getMachineToMachineToken')->once()->withNoArgs();
        });

        PricecypherAuth::getMachineToMachineToken();
    }

    /**
     * Verifies that the Auth0 facade can be faked and returns some machine to machine token when queried.
     */
    public function testFake()
    {
        // Enable testing mode.
        PricecypherAuth::fake();

        // Verify a machine token can be retrieved in testing mode without overwriting any of the defaults.
        self::assertIsString(PricecypherAuth::getMachineToMachineToken());
    }

    /**
     * Verifies that a fake expires in value can be set in testing mode.
     */
    public function testFakeSetExpiresIn()
    {
        // Enable testing mode
        PricecypherAuth::fake();

        // Call function under test.
        PricecypherAuth::fakeSetM2mExpiresIn(500);

        // Mock cache remember and verify called with TTL 500/2 = 250.
        Cache::shouldReceive('remember')->withArgs(function ($key, $ttl) {
            return value($ttl) == 250;
        })->once()->andReturn('unused');

        // Retrieve m2m function such that we can verify the cache call.
        PricecypherAuth::getMachineToMachineToken();
    }

    /**
     * Verifies that a fake access token can be set in testing mode.
     */
    public function testFakeSetAccessToken()
    {
        // Enable testing mode
        PricecypherAuth::fake();

        // Call function under test.
        PricecypherAuth::fakeSetM2mAccessToken('access_token_for_fake_set_access_token');

        // Verify token was indeed set.
        self::assertEquals('access_token_for_fake_set_access_token', PricecypherAuth::getMachineToMachineToken());
    }
}
