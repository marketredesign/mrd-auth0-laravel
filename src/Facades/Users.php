<?php


namespace Marketredesign\MrdAuth0Laravel\Facades;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use Marketredesign\MrdAuth0Laravel\Contracts\UserRepository;
use Marketredesign\MrdAuth0Laravel\Repository\Fakes\FakeUserRepository;

/**
 * @method static object|null get($id)
 * @method static void delete($id)
 * @method static Collection getByIds(Collection $ids, array $fields = null)
 * @method static Collection getByEmails(Collection $emails, array $fields = null)
 * @method static Collection getAllUsers()
 * @method static int fakeCount()
 * @method static void fakeClear()
 * @method static void fakeAddUsers(Collection $ids)
 *
 * @see UserRepository
 * @see FakeUserRepository
 */
class Users extends Facade
{
    public static function fake()
    {
        self::$app->singleton(UserRepository::class, FakeUserRepository::class);
    }

    /**
     * @inheritDocs
     */
    protected static function getFacadeAccessor()
    {
        return UserRepository::class;
    }
}
