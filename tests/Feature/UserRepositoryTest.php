<?php


namespace Marketredesign\MrdAuth0Laravel\Tests\Feature;

use Auth0\SDK\API\Authentication;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Application;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Marketredesign\MrdAuth0Laravel\Contracts\UserRepository;
use Marketredesign\MrdAuth0Laravel\Tests\TestCase;

class UserRepositoryTest extends TestCase
{
    /**
     * @var UserRepository
     */
    protected $repo;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the client_credentials method in the Authentication SDK such that it does not call Auth0's API.
        $this->mock(Authentication::class, function ($mock) {
            $mock->shouldReceive('client_credentials')->andReturn([
                'access_token' => 'token',
                'scope' => 'read:users',
                'expires_in' => 86400,
                'token_type' => 'Bearer',
            ]);
        });

        $this->repo = App::make(UserRepository::class);
    }

    /**
     * Define environment setup.
     *
     * @param  Application  $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        // Set the Laravel Auth0 config values which are used to some values.
        $app['config']->set('laravel-auth0', [
            'domain'     => 'auth.marketredesign.com',
            'client_id'  => '123',
            'client_secret' => 'secret',
            'guzzle_options' => $this->createTestingGuzzleOptions(),
        ]);
    }

    /**
     * Reset the repository singleton instance by creating a new instance.
     */
    protected function resetRepo()
    {
        App::forgetInstance(UserRepository::class);
        $this->repo = App::make(UserRepository::class);
    }

    /**
     * Verifies that our implementation of the User Repository is bound in the service container, and that it can be
     * instantiated when a correct config is present.
     */
    public function testServiceBinding()
    {
        // Verify it is indeed our instance.
        $this->assertInstanceOf(\Marketredesign\MrdAuth0Laravel\Repository\UserRepository::class, $this->repo);
    }

    /**
     * Verifies that getting a user by null ID does not error and returns null object.
     */
    public function testGetNull()
    {
        self::assertNull($this->repo->get(null));
    }

    /**
     * Verifies that getting a non existing user returns NULL without errors.
     */
    public function testGetNoneExisting()
    {
        // Generate 404 response on mocked auth0 management call.
        $this->mockedResponses = [new Response(404)];

        $user = $this->repo->get('sjaak');

        // User should be null when not found.
        self::assertNull($user);

        // Expect 1 api call.
        self::assertCount(1, $this->guzzleContainer);

        $request = $this->guzzleContainer[0]['request'];

        // Verify correct endpoint was called.
        self::assertEquals('/api/v2/users/sjaak', $request->getUri()->getPath());
    }

    /**
     * Verifies that retrieving a single, existing user works as expected.
     */
    public function testGetExisting()
    {
        // Return example response, taken from Auth0 documentation.
        $this->mockedResponses = [new Response(200, [], '{"user_id":"auth0|507f1f77bcf86cd799439020",
        "email":"john.doe@gmail.com","email_verified":false,"username":"johndoe","phone_number":"+199999999999999",
        "phone_verified":false,"created_at":"","updated_at":"","identities":[{"connection":"Initial-Connection",
        "user_id":"507f1f77bcf86cd799439020","provider":"auth0","isSocial":false}],"app_metadata":{},"user_metadata":{},
        "picture":"","name":"","nickname":"","multifactor":[""],"last_ip":"","last_login":"","logins_count":0,
        "blocked":false,"given_name":"","family_name":""}')];

        // Execute function under test
        $user = $this->repo->get('auth0|507f1f77bcf86cd799439020');

        self::assertNotNull($user);
        self::assertEquals('auth0|507f1f77bcf86cd799439020', $user->user_id);
        self::assertEquals('john.doe@gmail.com', $user->email);
        self::assertEquals('johndoe', $user->username);
        self::assertEquals('507f1f77bcf86cd799439020', $user->identities[0]->user_id);

        // Expect 1 api call.
        self::assertCount(1, $this->guzzleContainer);

        // Find the request that was sent to Auth0
        $request = $this->guzzleContainer[0]['request'];

        // Verify correct endpoint was called.
        $urlencodedId = urlencode('auth0|507f1f77bcf86cd799439020');
        self::assertEquals("/api/v2/users/{$urlencodedId}", $request->getUri()->getPath());
    }

    /**
     * Verifies that retrieving different users in subsequent calls works.
     */
    public function testGetDifferentUsers()
    {
        // Return two different responses on subsequent calls.
        $this->mockedResponses = [
            new Response(200, [], '{"user_id":"auth0|507f1f77bcf86cd799439020",
                "email":"john.doe@gmail.com","email_verified":false,"username":"johndoe","phone_number":
                "+199999999999999","phone_verified":false,"created_at":"","updated_at":"","identities":[{"connection":
                "Initial-Connection","user_id":"507f1f77bcf86cd799439020","provider":"auth0","isSocial":false}],
                "app_metadata":{},"user_metadata":{},"picture":"","name":"","nickname":"","multifactor":[""],
                "last_ip":"","last_login":"","logins_count":0,"blocked":false,"given_name":"","family_name":""}'),
            new Response(200, [], '{"user_id":"some_user_id",
                "email":"different@gmail.com","email_verified":false,"username":"testing_user","phone_number":
                "+199999999999999","phone_verified":false,"created_at":"","updated_at":"","identities":[{"connection":
                "Initial-Connection","user_id":"507f1f77bcf86cd799439020","provider":"auth0","isSocial":false}],
                "app_metadata":{},"user_metadata":{},"picture":"","name":"","nickname":"","multifactor":[""],
                "last_ip":"","last_login":"","logins_count":0,"blocked":false,"given_name":"","family_name":""}'),
        ];

        // Execute function under test twice, for different users
        $user1 = $this->repo->get('auth0|507f1f77bcf86cd799439020');
        $user2 = $this->repo->get('some_user_id');

        // Verify 1st user object.
        self::assertNotNull($user1);
        self::assertEquals('auth0|507f1f77bcf86cd799439020', $user1->user_id);
        self::assertEquals('johndoe', $user1->username);

        // Verify 2nd user object.
        self::assertNotNull($user2);
        self::assertEquals('some_user_id', $user2->user_id);
        self::assertEquals('testing_user', $user2->username);

        // Expect 2 api calls.
        self::assertCount(2, $this->guzzleContainer);

        // Find the requests that were sent to Auth0
        $request1 = $this->guzzleContainer[0]['request'];
        $request2 = $this->guzzleContainer[1]['request'];

        // Verify correct endpoints were called.
        $urlencodedId = urlencode('auth0|507f1f77bcf86cd799439020');
        self::assertEquals("/api/v2/users/{$urlencodedId}", $request1->getUri()->getPath());
        self::assertEquals("/api/v2/users/some_user_id", $request2->getUri()->getPath());
    }

    /**
     * Verifies that the underlying API is only called once for subsequent retrievals for the same user ID.
     */
    public function testGetCaching()
    {
        // Return example response, taken from Auth0 documentation.
        $this->mockedResponses = [new Response(200, [], '{"user_id":"auth0|507f1f77bcf86cd799439020",
        "email":"john.doe@gmail.com","email_verified":false,"username":"johndoe","phone_number":"+199999999999999",
        "phone_verified":false,"created_at":"","updated_at":"","identities":[{"connection":"Initial-Connection",
        "user_id":"507f1f77bcf86cd799439020","provider":"auth0","isSocial":false}],"app_metadata":{},"user_metadata":{},
        "picture":"","name":"","nickname":"","multifactor":[""],"last_ip":"","last_login":"","logins_count":0,
        "blocked":false,"given_name":"","family_name":""}')];

        // Execute function under test twice for the same user ID.
        $user = $this->repo->get('auth0|507f1f77bcf86cd799439020');
        $user2 = $this->repo->get('auth0|507f1f77bcf86cd799439020');

        // Verify the same object is returned.
        self::assertEquals($user, $user2);

        // Verify only 1 api call was made.
        self::assertCount(1, $this->guzzleContainer);
    }

    /**
     * Verifies that the cache TTL can be set using a config value for get single user by ID.
     */
    public function testGetCachingTTL()
    {
        // Return example response twice times, taken from Auth0 documentation.
        $response = new Response(200, [], '{"user_id":"auth0|507f1f77bcf86cd799439020",
        "email":"john.doe@gmail.com","email_verified":false,"username":"johndoe","phone_number":"+199999999999999",
        "phone_verified":false,"created_at":"","updated_at":"","identities":[{"connection":"Initial-Connection",
        "user_id":"507f1f77bcf86cd799439020","provider":"auth0","isSocial":false}],"app_metadata":{},"user_metadata":{},
        "picture":"","name":"","nickname":"","multifactor":[""],"last_ip":"","last_login":"","logins_count":0,
        "blocked":false,"given_name":"","family_name":""}');
        $this->mockedResponses = [$response, $response];

        // Set cache TTL to 10 seconds in the config, and create new repository using this cache value.
        Config::set('mrd-auth0.cache_ttl', 10);
        $this->resetRepo();

        // First execute function under test twice, without delay, for the same user ID.
        $this->repo->get('auth0|507f1f77bcf86cd799439020');
        $this->repo->get('auth0|507f1f77bcf86cd799439020');

        // Verify only 1 api call was made.
        self::assertCount(1, $this->guzzleContainer);

        // Increment time such that cache TTL should have passed.
        Carbon::setTestNow(Carbon::now()->addSeconds(11));

        // Execute function under test again, and expect a new API call to be made.
        $this->repo->get('auth0|507f1f77bcf86cd799439020');

        // Verify now a total of two api calls was made.
        self::assertCount(2, $this->guzzleContainer);
    }

    /**
     * Verifies that getting users by ID returns empty collection when no IDs are given.
     */
    public function testGetByIdEmpty()
    {
        $this->mockedResponses = [new Response(200, [], '[]')];

        // Call function under test.
        $users = $this->repo->getByIds(collect());

        // Users should be empty.
        self::assertTrue($users->isEmpty());

        // Expect 1 api call.
        self::assertCount(1, $this->guzzleContainer);

        $request = $this->guzzleContainer[0]['request'];

        // Verify correct endpoint was called.
        self::assertEquals('/api/v2/users', $request->getUri()->getPath());
        // Verify that no user IDs were requested.
        self::assertStringContainsString('user_id:("")', urldecode($request->getUri()->getQuery()));
    }

    /**
     * Verifies that retrieving by ID for one user works as expected.
     */
    public function testGetByIdOne()
    {
        // Response from Auth0 API documentation
        $this->mockedResponses = [new Response(200, [], '[{"user_id":"auth0|507f1f77bcf86cd799439020",
        "email":"john.doe@gmail.com","email_verified":false,"username":"johndoe","phone_number":"+199999999999999",
        "phone_verified":false,"created_at":"","updated_at":"","identities":[{"connection":"Initial-Connection",
        "user_id":"507f1f77bcf86cd799439020","provider":"auth0","isSocial":false}],"app_metadata":{},"user_metadata":{},
        "picture":"","name":"","nickname":"","multifactor":[""],"last_ip":"","last_login":"","logins_count":0,
        "blocked":false,"given_name":"","family_name":""}]')];

        // Call function under test.
        $users = $this->repo->getByIds(collect('auth0|507f1f77bcf86cd799439020'));

        // Expect one user returned.
        self::assertEquals(1, $users->count());
        // Verify that collection is keyed by user id.
        self::assertEquals('auth0|507f1f77bcf86cd799439020', $users->keys()->first());

        self::assertEquals('auth0|507f1f77bcf86cd799439020', $users->first()->user_id);
        self::assertEquals('john.doe@gmail.com', $users->first()->email);
        self::assertEquals('johndoe', $users->first()->username);
        self::assertEquals('507f1f77bcf86cd799439020', $users->first()->identities[0]->user_id);

        // Expect 1 api call.
        self::assertCount(1, $this->guzzleContainer);

        // Find the request that was sent to Auth0
        $request = $this->guzzleContainer[0]['request'];

        // Verify correct endpoint was called.
        self::assertEquals('/api/v2/users', $request->getUri()->getPath());
        // Verify that one ID was requested, and is as expected.
        self::assertStringContainsString(
            'q=user_id:("auth0|507f1f77bcf86cd799439020")',
            urldecode($request->getUri()->getQuery())
        );
    }

    /**
     * Verifies that retrieving by ID for two users works as expected.
     */
    public function testGetByIdMultiple()
    {
        // Response base on Auth0 API documentation
        $this->mockedResponses = [new Response(200, [], '[{"user_id":"auth0|507f1f77bcf86cd799439020",
        "email":"john.doe@gmail.com","email_verified":false,"username":"johndoe","phone_number":"+199999999999999",
        "phone_verified":false,"created_at":"","updated_at":"","identities":[{"connection":"Initial-Connection",
        "user_id":"507f1f77bcf86cd799439020","provider":"auth0","isSocial":false}],"app_metadata":{},"user_metadata":{},
        "picture":"","name":"","nickname":"","multifactor":[""],"last_ip":"","last_login":"","logins_count":0,
        "blocked":false,"given_name":"","family_name":""},{"user_id":"other_user",
        "email":"other@gmail.com","email_verified":false,"username":"other","phone_number":
        "+199999999999999","phone_verified":false,"created_at":"","updated_at":"","identities":[{"connection":
        "Initial-Connection","user_id":"507f1f77bcf86cd799439020","provider":"auth0","isSocial":false}],
        "app_metadata":{},"user_metadata":{},"picture":"","name":"","nickname":"","multifactor":[""],"last_ip":
        "","last_login":"","logins_count":0,"blocked":false,"given_name":"","family_name":""}]')];

        // Call function under test.
        $users = $this->repo->getByIds(collect(['auth0|507f1f77bcf86cd799439020', 'other_user']));

        // Expect one user returned.
        self::assertEquals(2, $users->count());
        // Verify keyed by user id.
        self::assertContains('auth0|507f1f77bcf86cd799439020', $users->keys());
        self::assertContains('other_user', $users->keys());

        // Verify two users
        $user1 = $users->get('auth0|507f1f77bcf86cd799439020');
        self::assertNotNull($user1);
        self::assertEquals('johndoe', $user1->username);

        $user2 = $users->get('other_user');
        self::assertNotNull($user2);
        self::assertEquals('other', $user2->username);

        // Expect 1 api call.
        self::assertCount(1, $this->guzzleContainer);

        // Find the request that was sent to Auth0
        $request = $this->guzzleContainer[0]['request'];
        $query = urldecode($request->getUri()->getQuery());

        // Verify correct endpoint was called.
        self::assertEquals('/api/v2/users', $request->getUri()->getPath());
        // Verify correct query sent to Auth0. Order of IDs does not matter.
        self::assertTrue(
            str_contains($query, 'q=user_id:("auth0|507f1f77bcf86cd799439020" OR "other_user")') ||
                str_contains($query, 'q=user_id:("other_user" OR "auth0|507f1f77bcf86cd799439020")'),
            $query
        );
    }

    /**
     * Verifies that result of retrieving by IDs can be scoped on a field.
     */
    public function testGetByIdFieldScoping()
    {
        // Altered response from Auth0 API documentation
        $this->mockedResponses = [new Response(200, [], '[{
            "family_name":"Tester",
            "given_name":"Sjaak",
            "user_id":"some_id"
        }]')];

        // Call function under test.
        $users = $this->repo->getByIds(collect('some_id'), ['family_name', 'given_name']);

        // Expect one user returned.
        self::assertEquals(1, $users->count());
        // Verify that collection is keyed by user id.
        self::assertEquals('some_id', $users->keys()->first());

        self::assertEquals('some_id', $users->first()->user_id);
        self::assertEquals('Tester', $users->first()->family_name);
        self::assertEquals('Sjaak', $users->first()->given_name);

        // Expect 1 api call.
        self::assertCount(1, $this->guzzleContainer);

        // Find the request that was sent to Auth0
        $request = $this->guzzleContainer[0]['request'];
        $queryVars = [];

        // Parse query parameter and store in queryVars.
        parse_str($request->getUri()->getQuery(), $queryVars);

        // Verify fields present in query.
        self::assertArrayHasKey('fields', $queryVars);

        // Find fields in query, and verify correct.
        $requestedFields = explode(',', $queryVars['fields']);
        self::assertCount(3, $requestedFields);
        self::assertContains('family_name', $requestedFields);
        self::assertContains('given_name', $requestedFields);
        self::assertContains('user_id', $requestedFields);

        // Verify correct endpoint was called.
        self::assertEquals('/api/v2/users', $request->getUri()->getPath());
        // Verify that one ID was requested, and is as expected.
        self::assertStringContainsString(
            'q=user_id:("some_id")',
            urldecode($request->getUri()->getQuery())
        );
    }

    /**
     * Verifies that the underlying API is only called once for subsequent retrievals for the same user ID, multiple.
     */
    public function testGetByIdsCaching()
    {
        // Response base on Auth0 API documentation
        $this->mockedResponses = [new Response(200, [], '[{"user_id":"auth0|507f1f77bcf86cd799439020",
        "email":"john.doe@gmail.com","email_verified":false,"username":"johndoe","phone_number":"+199999999999999",
        "phone_verified":false,"created_at":"","updated_at":"","identities":[{"connection":"Initial-Connection",
        "user_id":"507f1f77bcf86cd799439020","provider":"auth0","isSocial":false}],"app_metadata":{},"user_metadata":{},
        "picture":"","name":"","nickname":"","multifactor":[""],"last_ip":"","last_login":"","logins_count":0,
        "blocked":false,"given_name":"","family_name":""},{"user_id":"other_user",
        "email":"other@gmail.com","email_verified":false,"username":"other","phone_number":
        "+199999999999999","phone_verified":false,"created_at":"","updated_at":"","identities":[{"connection":
        "Initial-Connection","user_id":"507f1f77bcf86cd799439020","provider":"auth0","isSocial":false}],
        "app_metadata":{},"user_metadata":{},"picture":"","name":"","nickname":"","multifactor":[""],"last_ip":
        "","last_login":"","logins_count":0,"blocked":false,"given_name":"","family_name":""}]')];

        // Call function under test, twice.
        $users1 = $this->repo->getByIds(collect(['auth0|507f1f77bcf86cd799439020', 'other_user']));
        $users2 = $this->repo->getByIds(collect(['auth0|507f1f77bcf86cd799439020', 'other_user']));

        // Verify the same object is returned.
        self::assertEquals($users1, $users2);

        // Verify only 1 api call was made.
        self::assertCount(1, $this->guzzleContainer);
    }

    /**
     * Verifies that the cache TTL can be set using a config value for get multiple users by ID.
     */
    public function testGetByIdsCachingTTL()
    {
        // Response base on Auth0 API documentation
        $response = new Response(200, [], '[{"user_id":"auth0|507f1f77bcf86cd799439020",
        "email":"john.doe@gmail.com","email_verified":false,"username":"johndoe","phone_number":"+199999999999999",
        "phone_verified":false,"created_at":"","updated_at":"","identities":[{"connection":"Initial-Connection",
        "user_id":"507f1f77bcf86cd799439020","provider":"auth0","isSocial":false}],"app_metadata":{},"user_metadata":{},
        "picture":"","name":"","nickname":"","multifactor":[""],"last_ip":"","last_login":"","logins_count":0,
        "blocked":false,"given_name":"","family_name":""},{"user_id":"other_user",
        "email":"other@gmail.com","email_verified":false,"username":"other","phone_number":
        "+199999999999999","phone_verified":false,"created_at":"","updated_at":"","identities":[{"connection":
        "Initial-Connection","user_id":"507f1f77bcf86cd799439020","provider":"auth0","isSocial":false}],
        "app_metadata":{},"user_metadata":{},"picture":"","name":"","nickname":"","multifactor":[""],"last_ip":
        "","last_login":"","logins_count":0,"blocked":false,"given_name":"","family_name":""}]');
        $this->mockedResponses = [$response, $response];

        // Set cache TTL to 10 seconds in the config, and create new repository using this cache value.
        Config::set('mrd-auth0.cache_ttl', 10);
        $this->resetRepo();

        // First execute function under test twice, without delay.
        $this->repo->getByIds(collect(['auth0|507f1f77bcf86cd799439020', 'other_user']));
        $this->repo->getByIds(collect(['auth0|507f1f77bcf86cd799439020', 'other_user']));

        // Verify only 1 api call was made.
        self::assertCount(1, $this->guzzleContainer);

        // Increment time such that cache TTL should have passed.
        Carbon::setTestNow(Carbon::now()->addSeconds(11));

        // Execute function under test again, and expect a new API call to be made.
        $this->repo->getByIds(collect(['auth0|507f1f77bcf86cd799439020', 'other_user']));

        // Verify now a total of two api calls was made.
        self::assertCount(2, $this->guzzleContainer);
    }

    /**
     * Verifies that getting users by email returns empty collection when no emails are given.
     */
    public function testGetByEmailEmpty()
    {
        $this->mockedResponses = [new Response(200, [], '[]')];

        // Call function under test.
        $users = $this->repo->getByEmails(collect());

        // Users should be empty.
        self::assertTrue($users->isEmpty());

        // Expect 1 api call.
        self::assertCount(1, $this->guzzleContainer);

        $request = $this->guzzleContainer[0]['request'];

        // Verify correct endpoint was called.
        self::assertEquals('/api/v2/users', $request->getUri()->getPath());
        // Verify that no user emails were requested.
        self::assertStringContainsString('email:("")', urldecode($request->getUri()->getQuery()));
    }

    /**
     * Verifies that retrieving by email for one user works as expected.
     */
    public function testGetByEmailOne()
    {
        // Response from Auth0 API documentation
        $this->mockedResponses = [new Response(200, [], '[{"user_id":"auth0|507f1f77bcf86cd799439020",
        "email":"john.doe@gmail.com","email_verified":false,"username":"johndoe","phone_number":"+199999999999999",
        "phone_verified":false,"created_at":"","updated_at":"","identities":[{"connection":"Initial-Connection",
        "user_id":"507f1f77bcf86cd799439020","provider":"auth0","isSocial":false}],"app_metadata":{},"user_metadata":{},
        "picture":"","name":"","nickname":"","multifactor":[""],"last_ip":"","last_login":"","logins_count":0,
        "blocked":false,"given_name":"","family_name":""}]')];

        // Call function under test.
        $users = $this->repo->getByEmails(collect('john.doe@gmail.com'));

        // Expect one user returned.
        self::assertEquals(1, $users->count());
        // Verify that collection is keyed by email.
        self::assertEquals('john.doe@gmail.com', $users->keys()->first());

        self::assertEquals('auth0|507f1f77bcf86cd799439020', $users->first()->user_id);
        self::assertEquals('john.doe@gmail.com', $users->first()->email);
        self::assertEquals('johndoe', $users->first()->username);
        self::assertEquals('507f1f77bcf86cd799439020', $users->first()->identities[0]->user_id);

        // Expect 1 api call.
        self::assertCount(1, $this->guzzleContainer);

        // Find the request that was sent to Auth0
        $request = $this->guzzleContainer[0]['request'];

        // Verify correct endpoint was called.
        self::assertEquals('/api/v2/users', $request->getUri()->getPath());
        // Verify that one email was requested, and is as expected.
        self::assertStringContainsString(
            'q=email:("john.doe@gmail.com")',
            urldecode($request->getUri()->getQuery())
        );
    }

    /**
     * Verifies that retrieving by email for two users works as expected.
     */
    public function testGetByEmailMultiple()
    {
        // Response base on Auth0 API documentation
        $this->mockedResponses = [new Response(200, [], '[{"user_id":"auth0|507f1f77bcf86cd799439020",
        "email":"john.doe@gmail.com","email_verified":false,"username":"johndoe","phone_number":"+199999999999999",
        "phone_verified":false,"created_at":"","updated_at":"","identities":[{"connection":"Initial-Connection",
        "user_id":"507f1f77bcf86cd799439020","provider":"auth0","isSocial":false}],"app_metadata":{},"user_metadata":{},
        "picture":"","name":"","nickname":"","multifactor":[""],"last_ip":"","last_login":"","logins_count":0,
        "blocked":false,"given_name":"","family_name":""},{"user_id":"other_user",
        "email":"other@gmail.com","email_verified":false,"username":"other","phone_number":
        "+199999999999999","phone_verified":false,"created_at":"","updated_at":"","identities":[{"connection":
        "Initial-Connection","user_id":"507f1f77bcf86cd799439020","provider":"auth0","isSocial":false}],
        "app_metadata":{},"user_metadata":{},"picture":"","name":"","nickname":"","multifactor":[""],"last_ip":
        "","last_login":"","logins_count":0,"blocked":false,"given_name":"","family_name":""}]')];

        // Call function under test.
        $users = $this->repo->getByEmails(collect(['john.doe@gmail.com', 'other@gmail.com']));

        // Expect one user returned.
        self::assertEquals(2, $users->count());
        // Verify keyed by email.
        self::assertContains('john.doe@gmail.com', $users->keys());
        self::assertContains('other@gmail.com', $users->keys());

        // Verify two users
        $user1 = $users->get('john.doe@gmail.com');
        self::assertNotNull($user1);
        self::assertEquals('johndoe', $user1->username);

        $user2 = $users->get('other@gmail.com');
        self::assertNotNull($user2);
        self::assertEquals('other', $user2->username);

        // Expect 1 api call.
        self::assertCount(1, $this->guzzleContainer);

        // Find the request that was sent to Auth0
        $request = $this->guzzleContainer[0]['request'];
        $query = urldecode($request->getUri()->getQuery());

        // Verify correct endpoint was called.
        self::assertEquals('/api/v2/users', $request->getUri()->getPath());
        // Verify correct query sent to Auth0. Order of emails does not matter.
        self::assertTrue(
            str_contains($query, 'q=email:("john.doe@gmail.com" OR "other@gmail.com")') ||
            str_contains($query, 'q=email:("other@gmail.com" OR "john.doe@gmail.com")'),
            $query
        );
    }

    /**
     * Verifies that result of retrieving by emails can be scoped on a field.
     */
    public function testGetByEmailFieldScoping()
    {
        // Altered response from Auth0 API documentation
        $this->mockedResponses = [new Response(200, [], '[{
            "family_name":"Tester",
            "given_name":"Sjaak",
            "email":"some@mail.com"
        }]')];

        // Call function under test.
        $users = $this->repo->getByEmails(collect('some@mail.com'), ['family_name', 'given_name']);

        // Expect one user returned.
        self::assertEquals(1, $users->count());
        // Verify that collection is keyed by email.
        self::assertEquals('some@mail.com', $users->keys()->first());

        self::assertEquals('some@mail.com', $users->first()->email);
        self::assertEquals('Tester', $users->first()->family_name);
        self::assertEquals('Sjaak', $users->first()->given_name);

        // Expect 1 api call.
        self::assertCount(1, $this->guzzleContainer);

        // Find the request that was sent to Auth0
        $request = $this->guzzleContainer[0]['request'];
        $queryVars = [];

        // Parse query parameter and store in queryVars.
        parse_str($request->getUri()->getQuery(), $queryVars);

        // Verify fields present in query.
        self::assertArrayHasKey('fields', $queryVars);

        // Find fields in query, and verify correct.
        $requestedFields = explode(',', $queryVars['fields']);
        self::assertCount(3, $requestedFields);
        self::assertContains('family_name', $requestedFields);
        self::assertContains('given_name', $requestedFields);
        self::assertContains('email', $requestedFields);

        // Verify correct endpoint was called.
        self::assertEquals('/api/v2/users', $request->getUri()->getPath());
        // Verify that one email was requested, and is as expected.
        self::assertStringContainsString(
            'q=email:("some@mail.com")',
            urldecode($request->getUri()->getQuery())
        );
    }

    /**
     * Verifies that the underlying API is only called once for subsequent retrievals for the same email, multiple.
     */
    public function testGetByEmailsCaching()
    {
        // Response base on Auth0 API documentation
        $this->mockedResponses = [new Response(200, [], '[{"user_id":"auth0|507f1f77bcf86cd799439020",
        "email":"john.doe@gmail.com","email_verified":false,"username":"johndoe","phone_number":"+199999999999999",
        "phone_verified":false,"created_at":"","updated_at":"","identities":[{"connection":"Initial-Connection",
        "user_id":"507f1f77bcf86cd799439020","provider":"auth0","isSocial":false}],"app_metadata":{},"user_metadata":{},
        "picture":"","name":"","nickname":"","multifactor":[""],"last_ip":"","last_login":"","logins_count":0,
        "blocked":false,"given_name":"","family_name":""},{"user_id":"other_user",
        "email":"other@gmail.com","email_verified":false,"username":"other","phone_number":
        "+199999999999999","phone_verified":false,"created_at":"","updated_at":"","identities":[{"connection":
        "Initial-Connection","user_id":"507f1f77bcf86cd799439020","provider":"auth0","isSocial":false}],
        "app_metadata":{},"user_metadata":{},"picture":"","name":"","nickname":"","multifactor":[""],"last_ip":
        "","last_login":"","logins_count":0,"blocked":false,"given_name":"","family_name":""}]')];

        // Call function under test, twice.
        $users1 = $this->repo->getByIds(collect(['john.doe@gmail.com', 'other@gmail.com']));
        $users2 = $this->repo->getByIds(collect(['john.doe@gmail.com', 'other@gmail.com']));

        // Verify the same object is returned.
        self::assertEquals($users1, $users2);

        // Verify only 1 api call was made.
        self::assertCount(1, $this->guzzleContainer);
    }

    /**
     * Verifies that the cache TTL can be set using a config value for get multiple users by email.
     */
    public function testGetByEmailsCachingTTL()
    {
        // Response base on Auth0 API documentation
        $response = new Response(200, [], '[{"user_id":"auth0|507f1f77bcf86cd799439020",
        "email":"john.doe@gmail.com","email_verified":false,"username":"johndoe","phone_number":"+199999999999999",
        "phone_verified":false,"created_at":"","updated_at":"","identities":[{"connection":"Initial-Connection",
        "user_id":"507f1f77bcf86cd799439020","provider":"auth0","isSocial":false}],"app_metadata":{},"user_metadata":{},
        "picture":"","name":"","nickname":"","multifactor":[""],"last_ip":"","last_login":"","logins_count":0,
        "blocked":false,"given_name":"","family_name":""},{"user_id":"other_user",
        "email":"other@gmail.com","email_verified":false,"username":"other","phone_number":
        "+199999999999999","phone_verified":false,"created_at":"","updated_at":"","identities":[{"connection":
        "Initial-Connection","user_id":"507f1f77bcf86cd799439020","provider":"auth0","isSocial":false}],
        "app_metadata":{},"user_metadata":{},"picture":"","name":"","nickname":"","multifactor":[""],"last_ip":
        "","last_login":"","logins_count":0,"blocked":false,"given_name":"","family_name":""}]');
        $this->mockedResponses = [$response, $response];

        // Set cache TTL to 10 seconds in the config, and create new repository using this cache value.
        Config::set('mrd-auth0.cache_ttl', 10);
        $this->resetRepo();

        // First execute function under test twice, without delay.
        $this->repo->getByIds(collect(['john.doe@gmail.com', 'other@gmail.com']));
        $this->repo->getByIds(collect(['john.doe@gmail.com', 'other@gmail.com']));

        // Verify only 1 api call was made.
        self::assertCount(1, $this->guzzleContainer);

        // Increment time such that cache TTL should have passed.
        Carbon::setTestNow(Carbon::now()->addSeconds(11));

        // Execute function under test again, and expect a new API call to be made.
        $this->repo->getByIds(collect(['john.doe@gmail.com', 'other@gmail.com']));

        // Verify now a total of two api calls was made.
        self::assertCount(2, $this->guzzleContainer);
    }

    /**
     * Verifies that deletion works correctly
     */
    public function testDeleteOne()
    {
        // Altered response from Auth0 API documentation
        $this->mockedResponses = [new Response(204, [], '')];

        // Call function under test
        $userID = 'test';
        $response = $this->repo->delete($userID);

        // Find the request that was sent to Auth0
        $request = $this->guzzleContainer[0]['request'];

        // Expect a Delete request
        self::assertEquals("DELETE", $request->getMethod());

        // Expect 1 api call
        self::assertCount(1, $this->guzzleContainer);

        // Verify correct endpoint was called
        self::assertEquals('/api/v2/users/' . $userID, $request->getUri()->getPath());
    }

    public function testGetAllUsers()
    {
        $this->mockedResponses = [new Response(200, [], '[{"user_id":"auth0|507f1f77bcf86cd799439020",
        "email":"john.doe@gmail.com","email_verified":false,"username":"johndoe","phone_number":"+199999999999999",
        "phone_verified":false,"created_at":"","updated_at":"","identities":[{"connection":"Initial-Connection",
        "user_id":"507f1f77bcf86cd799439020","provider":"auth0","isSocial":false}],"app_metadata":{},"user_metadata":{},
        "picture":"","name":"","nickname":"","multifactor":[""],"last_ip":"","last_login":"","logins_count":0,
        "blocked":false,"given_name":"","family_name":""},{"user_id":"other_user",
        "email":"other@gmail.com","email_verified":false,"username":"other","phone_number":
        "+199999999999999","phone_verified":false,"created_at":"","updated_at":"","identities":[{"connection":
        "Initial-Connection","user_id":"507f1f77bcf86cd799439020","provider":"auth0","isSocial":false}],
        "app_metadata":{},"user_metadata":{},"picture":"","name":"","nickname":"","multifactor":[""],"last_ip":
        "","last_login":"","logins_count":0,"blocked":false,"given_name":"","family_name":""}]')];

        // Call function under test.
        $users = $this->repo->getAllUsers();

        // Expect one user returned.
        self::assertEquals(2, $users->count());

        // Verify keyed by user id.
        self::assertContains('auth0|507f1f77bcf86cd799439020', $users->keys());
        self::assertContains('other_user', $users->keys());

        // Verify two users
        $user1 = $users->get('auth0|507f1f77bcf86cd799439020');
        self::assertNotNull($user1);
        self::assertEquals('johndoe', $user1->username);

        $user2 = $users->get('other_user');
        self::assertNotNull($user2);
        self::assertEquals('other', $user2->username);

        // Expect 1 api call.
        self::assertCount(1, $this->guzzleContainer);

        // Find the request that was sent to Auth0
        $request = $this->guzzleContainer[0]['request'];

        // Verify correct endpoint was called.
        self::assertEquals('/api/v2/users', $request->getUri()->getPath());
    }
}
