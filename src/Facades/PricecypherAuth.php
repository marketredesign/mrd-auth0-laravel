<?php


namespace Marketredesign\MrdAuth0Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use Marketredesign\MrdAuth0Laravel\Contracts\AuthRepository;
use Marketredesign\MrdAuth0Laravel\Repository\Fakes\FakeAuthRepository;

/**
 * @method static string getMachineToMachineToken()
 * @method static void fakeSetM2mExpiresIn(int $expiresIn)
 * @method static void fakeSetM2mAccessToken(string $accessToken)
 *
 * @see AuthRepository
 * @see FakeAuthRepository
 */
class PricecypherAuth extends Facade
{
    public static function fake()
    {
        self::$app->singleton(AuthRepository::class, FakeAuthRepository::class);
    }

    /**
     * @inheritDocs
     */
    protected static function getFacadeAccessor()
    {
        return AuthRepository::class;
    }
}
