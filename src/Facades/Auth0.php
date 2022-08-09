<?php


namespace Marketredesign\MrdAuth0Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use Marketredesign\MrdAuth0Laravel\Contracts\Auth0Repository;
use Marketredesign\MrdAuth0Laravel\Repository\Fakes\FakeAuth0Repository;

/**
 * @method static string getMachineToMachineToken()
 * @method static void fakeSetM2mExpiresIn(int $expiresIn)
 * @method static void fakeSetM2mAccessToken(string $accessToken)
 *
 * @see Auth0Repository
 * @see FakeAuth0Repository
 */
class Auth0 extends Facade
{
    public static function fake()
    {
        self::$app->singleton(Auth0Repository::class, FakeAuth0Repository::class);
    }

    /**
     * @inheritDocs
     */
    protected static function getFacadeAccessor()
    {
        return Auth0Repository::class;
    }
}
