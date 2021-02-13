<?php


namespace Marketredesign\MrdAuth0Laravel\Tests\Feature;

use Marketredesign\MrdAuth0Laravel\Facades\Users;
use Marketredesign\MrdAuth0Laravel\Repository\UserRepository;
use Marketredesign\MrdAuth0Laravel\Tests\TestCase;

class UserFacadeTest extends TestCase
{
    /**
     * Verifies that get method is executed by our UserRepository implementation.
     */
    public function testGet()
    {
        $this->mock(UserRepository::class, function ($mock) {
            $mock->shouldReceive('get')->once()->with('some_id');
        });

        Users::get('some_id');
    }

    /**
     * Verifies that getByIds method is executed by our UserRepository implementation.
     */
    public function testGetByIds()
    {
        $this->mock(UserRepository::class, function ($mock) {
            $mock->shouldReceive('getByIds')->once();
        });

        Users::getByIds(collect(['some_id', 'other_id']));
    }

    /**
     * Verifies that getByEmails method is executed by our UserRepository implementation.
     */
    public function testGetByEmails()
    {
        $this->mock(UserRepository::class, function ($mock) {
            $mock->shouldReceive('getByEmails')->once();
        });

        Users::getByEmails(collect(['user@mail.com', 'test@gmail.com']));
    }

    /**
     * Verifies testing mode with adding fake users.
     */
    public function testFakeAddUsers()
    {
        // Enable testing mode.
        Users::fake();

        // Initially expect user to not exist.
        self::assertNull(Users::get('test'));

        // Add a fake user to the repository.
        Users::fakeAddUsers(collect('test'));

        // Retrieve user
        $user = Users::get('test');

        // Verify some user object was returned (has fake data).
        self::assertEquals('test', $user->user_id);
        self::assertNotEmpty($user->email);
        self::assertNotEmpty($user->given_name);
        self::assertNotEmpty($user->family_name);
    }

    /**
     * Verifies that, in testing mode, requesting the same user twice results in the same user object being returned.
     */
    public function testFakeRequestingSameUserTwice()
    {
        // Enable testing mode.
        Users::fake();

        // Add fake users to the repository.
        Users::fakeAddUsers(collect(['test1', 'test2']));

        // Retrieve first user twice
        $user1 = Users::get('test1');
        $user2 = Users::get('test1');

        // Verify some user object was returned (has fake data).
        self::assertNotNull($user1);
        self::assertEquals($user1, $user2);
    }

    /**
     * Verifies that the fake repository can be cleared.
     */
    public function testFakeClear()
    {
        // Enable testing mode.
        Users::fake();

        // Add a fake user to the repository.
        Users::fakeAddUsers(collect('test'));

        // Verify user exists.
        self::assertEquals('test', Users::get('test')->user_id);

        // Now clear the repository.
        Users::fakeClear();

        // Verify user does not exist anymore.
        self::assertNull(Users::get('test'));
    }

    /**
     * Verifies that the number of users in the fake repository can be counted.
     */
    public function testCount()
    {
        // Enable testing mode.
        Users::fake();

        // Add 3 fake users to the repository.
        Users::fakeAddUsers(collect(['test', 'sjaak', 'user2']));

        // Verify count.
        self::assertEquals(3, Users::fakeCount());

        // Now clear the repository.
        Users::fakeClear();

        // Verify count zero.
        self::assertEquals(0, Users::fakeCount());
    }

    /**
     * Verifies that users can be retrieved by multiple IDs from the fake repository.
     */
    public function testFakeGetByIds()
    {
        // Enable testing mode.
        Users::fake();

        // Add 3 fake users to the repository.
        Users::fakeAddUsers(collect(['test', 'sjaak', 'user2']));

        // Get two users.
        $users = Users::getByIds(collect(['sjaak', 'user2']));

        // Verify two users returned, and keyed by id.
        self::assertEquals(2, $users->count());
        self::assertContains('sjaak', $users->keys());
        self::assertContains('user2', $users->keys());

        // Verify users have filled fields.
        self::assertNotEmpty($users->get('sjaak')->email);
        self::assertNotEmpty($users->get('user2')->email);
        // Verify users have different fields.
        self::assertNotEquals($users->get('sjaak')->email, $users->get('user2')->email);
    }
}
