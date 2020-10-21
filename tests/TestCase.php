<?php


namespace Marketredesign\MrdAuth0Laravel\Tests;

use Auth0\Login\Auth0Service;
use Auth0\SDK\API\Authentication;
use Auth0\SDK\Exception\InvalidTokenException;
use Illuminate\Support\Arr;
use Marketredesign\MrdAuth0Laravel\MrdAuth0LaravelServiceProvider;

class TestCase extends \Orchestra\Testbench\TestCase
{
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
}
