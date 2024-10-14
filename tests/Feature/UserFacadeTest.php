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

        // Verify the user objects returned are the same.
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

    /**
     * Verifies that users can be retrieved through email address from the fake repository
     */
    public function testFakeGetByEmails()
    {
        // Enable testing mode
        Users::fake();

        // Add a fake user to the repository
        Users::fakeAddUsers(collect(1));

        // Get email of user
        $email = collect(Users::get(1)->email);

        // Get the users through email
        $users = Users::getByEmails($email);

        // Assert it returns the correct user,
        self::assertContains(1, $users->keys());
    }

    /**
     * Verifies that the fake delete functionality properly deletes users from the fake repository
     */
    public function testFakeDelete()
    {
        Users::fake();

        Users::fakeAddUsers(collect(['test', 'sjaak', 'user2']));
        Users::delete('sjaak');

        $users = Users::getByIds(collect(['test', 'sjaak', 'user2']));
        self::assertEquals(2, $users->count());
        self::assertContains('test', $users->keys());
        self::assertContains('user2', $users->keys());
    }

    /**
     * Verifies that fake create user functionality creates a new user and returns the new user properly
     */
    public function testCreateUser()
    {
        Users::fake();
        Users::fakeAddUsers(collect(['test', 'sjaak', 'user2']));

        $user = Users::createUser('foo@bar.com', 'foo', 'bar');

        self::assertEquals(4, Users::fakeCount());
        self::assertEquals('foo@bar.com', $user->email);
        self::assertEquals('foo', $user->given_name);
        self::assertEquals('bar', $user->family_name);
        self::assertEquals('foo'.' '.'bar', $user->name);
    }

    /**
     * Verifies that no users are returned when there are no users in the fake repository
     */
    public function testFakeGetAllUsersNone()
    {
        Users::fake();

        $users = Users::getAllUsers();
        self::assertEquals(0, $users->count());
    }

    /**
     * Verifies that all users are returned when there are multiple users in the fake repository
     */
    public function testFakeGetAllUsersMultiple()
    {
        Users::fake();
        Users::fakeAddUsers(collect(['test', 'sjaak', 'user2']));

        $users = Users::getAllUsers();

        // check that all users are returned
        self::assertEquals(3, $users->count());
        self::assertContains('test', $users->keys());
        self::assertContains('sjaak', $users->keys());
        self::assertContains('user2', $users->keys());

        // Verify users have filled fields.
        self::assertNotEmpty($users->get('test')->email);
        self::assertNotEmpty($users->get('sjaak')->email);
        self::assertNotEmpty($users->get('user2')->email);

        // Verify users have different fields.
        self::assertNotEquals($users->get('sjaak')->email, $users->get('user2')->email);
    }

    /**
     * Verifies that the get, add, and remove roles functions work as expected when in fake mode.
     */
    public function testFakeRoles()
    {
        // Enable testing mode
        Users::fake();

        // Verify initially no roles for users.
        self::assertCount(0, Users::getRoles('user1'));
        self::assertCount(0, Users::getRoles('user2'));

        // Add fake roles to same fake users.
        Users::addRoles('user2', collect(['role1', 'role2', 'role4']));
        Users::addRoles('user1', collect(['role3']));

        // Verify roles user 1.
        self::assertCount(1, Users::getRoles('user1'));
        self::assertContains('role3', Users::getRoles('user1'));

        // Verify roles user 2.
        self::assertCount(3, Users::getRoles('user2'));
        self::assertContains('role1', Users::getRoles('user2'));
        self::assertContains('role2', Users::getRoles('user2'));
        self::assertContains('role4', Users::getRoles('user2'));

        // Remove some roles.
        Users::removeRoles('user1', collect(['role3']));
        Users::removeRoles('user2', collect(['role1', 'role4']));

        // Verify roles after removal.
        self::assertCount(0, Users::getRoles('user1'));
        self::assertCount(1, Users::getRoles('user2'));
        self::assertContains('role2', Users::getRoles('user2'));
    }
}
