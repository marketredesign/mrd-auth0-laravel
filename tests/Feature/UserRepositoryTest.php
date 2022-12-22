<?php


namespace Marketredesign\MrdAuth0Laravel\Tests\Feature;

use Auth0\Laravel\Facade\Auth0;
use Auth0\Laravel\Store\LaravelSession;
use GuzzleHttp\Psr7\Response;
use Http\Mock\Client;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Marketredesign\MrdAuth0Laravel\Contracts\UserRepository;
use Marketredesign\MrdAuth0Laravel\Tests\TestCase;

class UserRepositoryTest extends TestCase
{
    /**
     * @var UserRepository
     */
    protected $repo;

    protected Client $httpClient;

    protected function setUp(): void
    {
        parent::setUp();

        // Let the Auth0 SDK use our mocked HTTP client.
        $this->httpClient = new Client();

        Config::set('auth0', [
            'domain'   => 'auth.marketredesign.com',
            'audience' => ['https://api.pricecypher.com'],
            'redirectUri' => 'https://redirect.com/oauth/callback',
            'sessionStorage' => new LaravelSession(),
            'transientStorage' => new LaravelSession(),
            'clientId' => '123',
            'cookieSecret' => 'secret',
            'managementToken' => 'token',
            'httpClient' => $this->httpClient,
        ]);

        $this->resetAuth0Config();

        $this->repo = App::make(UserRepository::class);
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
        Auth0::getSdk()->management()->getHttpClient()
            ->mockResponse(new Response(404));

        $user = $this->repo->get('sjaak');

        // User should be null when not found.
        self::assertNull($user);

        // Expect 1 api call.
        self::assertCount(1, $this->httpClient->getRequests());

        $request = Auth0::getSdk()->management()->getHttpClient()->getLastRequest();

        // Verify correct endpoint was called.
        self::assertEquals('users/sjaak', $request->getUrl());
    }

    /**
     * Verifies that retrieving a single, existing user works as expected.
     */
    public function testGetExisting()
    {
        // Return example response, taken from Auth0 documentation.
        Auth0::getSdk()->management()->getHttpClient()
            ->mockResponse(new Response(200, [], '{"user_id":"auth0|507f1f77bcf86cd799439020",
        "email":"john.doe@gmail.com","email_verified":false,"username":"johndoe","phone_number":"+199999999999999",
        "phone_verified":false,"created_at":"","updated_at":"","identities":[{"connection":"Initial-Connection",
        "user_id":"507f1f77bcf86cd799439020","provider":"auth0","isSocial":false}],"app_metadata":{},"user_metadata":{},
        "picture":"","name":"","nickname":"","multifactor":[""],"last_ip":"","last_login":"","logins_count":0,
        "blocked":false,"given_name":"","family_name":""}'));

        // Execute function under test
        $user = $this->repo->get('auth0|507f1f77bcf86cd799439020');

        self::assertNotNull($user);
        self::assertEquals('auth0|507f1f77bcf86cd799439020', $user->user_id);
        self::assertEquals('john.doe@gmail.com', $user->email);
        self::assertEquals('johndoe', $user->username);
        self::assertEquals('507f1f77bcf86cd799439020', $user->identities[0]->user_id);

        // Expect 1 api call.
        self::assertCount(1, $this->httpClient->getRequests());

        // Find the request that was sent to Auth0
        $request = Auth0::getSdk()->management()->getHttpClient()->getLastRequest();

        // Verify correct endpoint was called.
        self::assertEquals("users/auth0|507f1f77bcf86cd799439020", $request->getUrl());
    }

    /**
     * Verifies that retrieving different users in subsequent calls works.
     */
    public function testGetDifferentUsers()
    {
        // Return two different responses on subsequent calls.
        Auth0::getSdk()->management()->getHttpClient()
            ->mockResponse(new Response(200, [], '{"user_id":"auth0|507f1f77bcf86cd799439020",
                "email":"john.doe@gmail.com","email_verified":false,"username":"johndoe","phone_number":
                "+199999999999999","phone_verified":false,"created_at":"","updated_at":"","identities":[{"connection":
                "Initial-Connection","user_id":"507f1f77bcf86cd799439020","provider":"auth0","isSocial":false}],
                "app_metadata":{},"user_metadata":{},"picture":"","name":"","nickname":"","multifactor":[""],
                "last_ip":"","last_login":"","logins_count":0,"blocked":false,"given_name":"","family_name":""}'))
            ->mockResponse(new Response(200, [], '{"user_id":"some_user_id",
                "email":"different@gmail.com","email_verified":false,"username":"testing_user","phone_number":
                "+199999999999999","phone_verified":false,"created_at":"","updated_at":"","identities":[{"connection":
                "Initial-Connection","user_id":"507f1f77bcf86cd799439020","provider":"auth0","isSocial":false}],
                "app_metadata":{},"user_metadata":{},"picture":"","name":"","nickname":"","multifactor":[""],
                "last_ip":"","last_login":"","logins_count":0,"blocked":false,"given_name":"","family_name":""}'));

        // Execute function under test twice, for different users. Each time finding the request that was sent.
        $user1 = $this->repo->get('auth0|507f1f77bcf86cd799439020');
        $request1 = Auth0::getSdk()->management()->getHttpClient()->getLastRequest();

        $user2 = $this->repo->get('some_user_id');
        $request2 = Auth0::getSdk()->management()->getHttpClient()->getLastRequest();

        // Verify 1st user object.
        self::assertNotNull($user1);
        self::assertEquals('auth0|507f1f77bcf86cd799439020', $user1->user_id);
        self::assertEquals('johndoe', $user1->username);

        // Verify 2nd user object.
        self::assertNotNull($user2);
        self::assertEquals('some_user_id', $user2->user_id);
        self::assertEquals('testing_user', $user2->username);

        // Expect 2 api calls.
        self::assertCount(2, $this->httpClient->getRequests());

        // Verify correct endpoints were called.
        self::assertEquals("users/auth0|507f1f77bcf86cd799439020", $request1->getUrl());
        self::assertEquals("users/some_user_id", $request2->getUrl());
    }

    /**
     * Verifies that the underlying API is only called once for subsequent retrievals for the same user ID.
     */
    public function testGetCaching()
    {
        // Return example response, taken from Auth0 documentation.
        Auth0::getSdk()->management()->getHttpClient()
            ->mockResponse(new Response(200, [], '{"user_id":"auth0|507f1f77bcf86cd799439020",
        "email":"john.doe@gmail.com","email_verified":false,"username":"johndoe","phone_number":"+199999999999999",
        "phone_verified":false,"created_at":"","updated_at":"","identities":[{"connection":"Initial-Connection",
        "user_id":"507f1f77bcf86cd799439020","provider":"auth0","isSocial":false}],"app_metadata":{},"user_metadata":{},
        "picture":"","name":"","nickname":"","multifactor":[""],"last_ip":"","last_login":"","logins_count":0,
        "blocked":false,"given_name":"","family_name":""}'));

        // Execute function under test twice for the same user ID.
        $user = $this->repo->get('auth0|507f1f77bcf86cd799439020');
        $user2 = $this->repo->get('auth0|507f1f77bcf86cd799439020');

        // Verify the same object is returned.
        self::assertEquals($user, $user2);

        // Verify only 1 api call was made.
        self::assertCount(1, $this->httpClient->getRequests());
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
        Auth0::getSdk()->management()->getHttpClient()
            ->mockResponses([clone $response, clone $response]);

        // Set cache TTL to 10 seconds in the config, and create new repository using this cache value.
        Config::set('mrd-auth0.cache_ttl', 10);
        $this->resetRepo();

        // First execute function under test twice, without delay, for the same user ID.
        $this->repo->get('auth0|507f1f77bcf86cd799439020');
        $this->repo->get('auth0|507f1f77bcf86cd799439020');

        // Verify only 1 api call was made.
        self::assertCount(1, $this->httpClient->getRequests());

        // Increment time such that cache TTL should have passed.
        Carbon::setTestNow(Carbon::now()->addSeconds(11));

        // Execute function under test again, and expect a new API call to be made.
        $this->repo->get('auth0|507f1f77bcf86cd799439020');

        // Verify now a total of two api calls was made.
        self::assertCount(2, $this->httpClient->getRequests());
    }

    /**
     * Verifies that getting users by ID returns empty collection when no IDs are given.
     */
    public function testGetByIdEmpty()
    {
        // Call function under test.
        $users = $this->repo->getByIds(collect());

        // Users should be empty.
        self::assertTrue($users->isEmpty());

        // Expect no api calls.
        self::assertCount(0, $this->guzzleContainer);
    }

    /**
     * Verifies that retrieving by ID for one user works as expected.
     */
    public function testGetByIdOne()
    {
        // Response from Auth0 API documentation
        Auth0::getSdk()->management()->getHttpClient()
            ->mockResponse(new Response(200, [], '[{"user_id":"auth0|507f1f77bcf86cd799439020",
        "email":"john.doe@gmail.com","email_verified":false,"username":"johndoe","phone_number":"+199999999999999",
        "phone_verified":false,"created_at":"","updated_at":"","identities":[{"connection":"Initial-Connection",
        "user_id":"507f1f77bcf86cd799439020","provider":"auth0","isSocial":false}],"app_metadata":{},"user_metadata":{},
        "picture":"","name":"","nickname":"","multifactor":[""],"last_ip":"","last_login":"","logins_count":0,
        "blocked":false,"given_name":"","family_name":""}]'));

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
        self::assertCount(1, $this->httpClient->getRequests());

        // Find the request that was sent to Auth0
        $request = Auth0::getSdk()->management()->getHttpClient()->getLastRequest();

        // Verify correct endpoint was called.
        self::assertStringStartsWith('users?', $request->getUrl());
        // Verify that one ID was requested, and is as expected.
        self::assertStringContainsString(
            'q=user_id:("auth0|507f1f77bcf86cd799439020")',
            urldecode($request->getParams())
        );
    }

    /**
     * Verifies that retrieving by ID for two users works as expected.
     */
    public function testGetByIdMultiple()
    {
        // Response base on Auth0 API documentation
        Auth0::getSdk()->management()->getHttpClient()
            ->mockResponse(new Response(200, [], '[{"user_id":"auth0|507f1f77bcf86cd799439020",
        "email":"john.doe@gmail.com","email_verified":false,"username":"johndoe","phone_number":"+199999999999999",
        "phone_verified":false,"created_at":"","updated_at":"","identities":[{"connection":"Initial-Connection",
        "user_id":"507f1f77bcf86cd799439020","provider":"auth0","isSocial":false}],"app_metadata":{},"user_metadata":{},
        "picture":"","name":"","nickname":"","multifactor":[""],"last_ip":"","last_login":"","logins_count":0,
        "blocked":false,"given_name":"","family_name":""},{"user_id":"other_user",
        "email":"other@gmail.com","email_verified":false,"username":"other","phone_number":
        "+199999999999999","phone_verified":false,"created_at":"","updated_at":"","identities":[{"connection":
        "Initial-Connection","user_id":"507f1f77bcf86cd799439020","provider":"auth0","isSocial":false}],
        "app_metadata":{},"user_metadata":{},"picture":"","name":"","nickname":"","multifactor":[""],"last_ip":
        "","last_login":"","logins_count":0,"blocked":false,"given_name":"","family_name":""}]'));

        // Call function under test.
        $users = $this->repo->getByIds(collect(['auth0|507f1f77bcf86cd799439020', 'other_user']));

        // Expect two users returned.
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
        // Expect 1 api call.
        self::assertCount(1, $this->httpClient->getRequests());

        // Find the request that was sent to Auth0
        $request = Auth0::getSdk()->management()->getHttpClient()->getLastRequest();
        $query = urldecode($request->getParams());

        // Verify correct endpoint was called.
        self::assertStringStartsWith('users?', $request->getUrl());
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
        Auth0::getSdk()->management()->getHttpClient()
            ->mockResponse(new Response(200, [], '[{
            "family_name":"Tester",
            "given_name":"Sjaak",
            "user_id":"some_id"
        }]'));

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
        self::assertCount(1, $this->httpClient->getRequests());

        // Find the request that was sent to Auth0
        $request = Auth0::getSdk()->management()->getHttpClient()->getLastRequest();
        $queryVars = [];

        // Parse query parameter and store in queryVars.
        parse_str($request->getParams(), $queryVars);

        // Verify fields present in query.
        self::assertArrayHasKey('fields', $queryVars);

        // Find fields in query, and verify correct.
        $requestedFields = explode(',', $queryVars['fields']);
        self::assertCount(3, $requestedFields);
        self::assertContains('family_name', $requestedFields);
        self::assertContains('given_name', $requestedFields);
        self::assertContains('user_id', $requestedFields);

        // Verify correct endpoint was called.
        self::assertStringStartsWith('users?', $request->getUrl());
        // Verify that one ID was requested, and is as expected.
        self::assertStringContainsString(
            'q=user_id:("some_id")',
            urldecode($request->getParams())
        );
    }

    /**
     * Verifies that the underlying API is only called once for subsequent retrievals for the same user ID, multiple.
     */
    public function testGetByIdsCaching()
    {
        // Response base on Auth0 API documentation
        Auth0::getSdk()->management()->getHttpClient()
            ->mockResponse(new Response(200, [], '[{"user_id":"auth0|507f1f77bcf86cd799439020",
        "email":"john.doe@gmail.com","email_verified":false,"username":"johndoe","phone_number":"+199999999999999",
        "phone_verified":false,"created_at":"","updated_at":"","identities":[{"connection":"Initial-Connection",
        "user_id":"507f1f77bcf86cd799439020","provider":"auth0","isSocial":false}],"app_metadata":{},"user_metadata":{},
        "picture":"","name":"","nickname":"","multifactor":[""],"last_ip":"","last_login":"","logins_count":0,
        "blocked":false,"given_name":"","family_name":""},{"user_id":"other_user",
        "email":"other@gmail.com","email_verified":false,"username":"other","phone_number":
        "+199999999999999","phone_verified":false,"created_at":"","updated_at":"","identities":[{"connection":
        "Initial-Connection","user_id":"507f1f77bcf86cd799439020","provider":"auth0","isSocial":false}],
        "app_metadata":{},"user_metadata":{},"picture":"","name":"","nickname":"","multifactor":[""],"last_ip":
        "","last_login":"","logins_count":0,"blocked":false,"given_name":"","family_name":""}]'));

        // Call function under test, twice.
        $users1 = $this->repo->getByIds(collect(['auth0|507f1f77bcf86cd799439020', 'other_user']));
        $users2 = $this->repo->getByIds(collect(['auth0|507f1f77bcf86cd799439020', 'other_user']));

        // Verify the same object is returned.
        self::assertEquals($users1, $users2);

        // Verify only 1 api call was made.
        self::assertCount(1, $this->httpClient->getRequests());
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
        Auth0::getSdk()->management()->getHttpClient()
            ->mockResponses([clone $response, clone $response]);

        // Set cache TTL to 10 seconds in the config, and create new repository using this cache value.
        Config::set('mrd-auth0.cache_ttl', 10);
        $this->resetRepo();

        // First execute function under test twice, without delay.
        $this->repo->getByIds(collect(['auth0|507f1f77bcf86cd799439020', 'other_user']));
        $this->repo->getByIds(collect(['auth0|507f1f77bcf86cd799439020', 'other_user']));

        // Verify only 1 api call was made.
        self::assertCount(1, $this->httpClient->getRequests());

        // Increment time such that cache TTL should have passed.
        Carbon::setTestNow(Carbon::now()->addSeconds(11));

        // Execute function under test again, and expect a new API call to be made.
        $this->repo->getByIds(collect(['auth0|507f1f77bcf86cd799439020', 'other_user']));

        // Verify now a total of two api calls was made.
        self::assertCount(2, $this->httpClient->getRequests());
    }

    /**
     * Verifies that getting users by email returns empty collection when no emails are given.
     */
    public function testGetByEmailEmpty()
    {
        // Call function under test.
        $users = $this->repo->getByEmails(collect());

        // Users should be empty.
        self::assertTrue($users->isEmpty());

        // Expect no api calls.
        self::assertCount(0, $this->guzzleContainer);
    }

    /**
     * Verifies that retrieving by email for one user works as expected.
     */
    public function testGetByEmailOne()
    {
        // Response from Auth0 API documentation
        Auth0::getSdk()->management()->getHttpClient()
            ->mockResponse(new Response(200, [], '[{"user_id":"auth0|507f1f77bcf86cd799439020",
        "email":"john.doe@gmail.com","email_verified":false,"username":"johndoe","phone_number":"+199999999999999",
        "phone_verified":false,"created_at":"","updated_at":"","identities":[{"connection":"Initial-Connection",
        "user_id":"507f1f77bcf86cd799439020","provider":"auth0","isSocial":false}],"app_metadata":{},"user_metadata":{},
        "picture":"","name":"","nickname":"","multifactor":[""],"last_ip":"","last_login":"","logins_count":0,
        "blocked":false,"given_name":"","family_name":""}]'));

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
        self::assertCount(1, $this->httpClient->getRequests());

        // Find the request that was sent to Auth0
        $request = Auth0::getSdk()->management()->getHttpClient()->getLastRequest();

        // Verify correct endpoint was called.
        self::assertStringStartsWith('users?', $request->getUrl());
        // Verify that one email was requested, and is as expected.
        self::assertStringContainsString(
            'q=email:("john.doe@gmail.com")',
            urldecode($request->getParams())
        );
    }

    /**
     * Verifies that retrieving by email for two users works as expected.
     */
    public function testGetByEmailMultiple()
    {
        // Response base on Auth0 API documentation
        Auth0::getSdk()->management()->getHttpClient()
            ->mockResponse(new Response(200, [], '[{"user_id":"auth0|507f1f77bcf86cd799439020",
        "email":"john.doe@gmail.com","email_verified":false,"username":"johndoe","phone_number":"+199999999999999",
        "phone_verified":false,"created_at":"","updated_at":"","identities":[{"connection":"Initial-Connection",
        "user_id":"507f1f77bcf86cd799439020","provider":"auth0","isSocial":false}],"app_metadata":{},"user_metadata":{},
        "picture":"","name":"","nickname":"","multifactor":[""],"last_ip":"","last_login":"","logins_count":0,
        "blocked":false,"given_name":"","family_name":""},{"user_id":"other_user",
        "email":"other@gmail.com","email_verified":false,"username":"other","phone_number":
        "+199999999999999","phone_verified":false,"created_at":"","updated_at":"","identities":[{"connection":
        "Initial-Connection","user_id":"507f1f77bcf86cd799439020","provider":"auth0","isSocial":false}],
        "app_metadata":{},"user_metadata":{},"picture":"","name":"","nickname":"","multifactor":[""],"last_ip":
        "","last_login":"","logins_count":0,"blocked":false,"given_name":"","family_name":""}]'));

        // Call function under test.
        $users = $this->repo->getByEmails(collect(['john.doe@gmail.com', 'other@gmail.com']));

        // Expect two users returned.
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
        self::assertCount(1, $this->httpClient->getRequests());

        // Find the request that was sent to Auth0
        $request = Auth0::getSdk()->management()->getHttpClient()->getLastRequest();
        $query = urldecode($request->getParams());

        // Verify correct endpoint was called.
        self::assertStringStartsWith('users?', $request->getUrl());
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
        Auth0::getSdk()->management()->getHttpClient()
            ->mockResponse(new Response(200, [], '[{
            "family_name":"Tester",
            "given_name":"Sjaak",
            "email":"some@mail.com"
        }]'));

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
        self::assertCount(1, $this->httpClient->getRequests());

        // Find the request that was sent to Auth0
        $request = Auth0::getSdk()->management()->getHttpClient()->getLastRequest();
        $queryVars = [];

        // Parse query parameter and store in queryVars.
        parse_str($request->getParams(), $queryVars);

        // Verify fields present in query.
        self::assertArrayHasKey('fields', $queryVars);

        // Find fields in query, and verify correct.
        $requestedFields = explode(',', $queryVars['fields']);
        self::assertCount(3, $requestedFields);
        self::assertContains('family_name', $requestedFields);
        self::assertContains('given_name', $requestedFields);
        self::assertContains('email', $requestedFields);

        // Verify correct endpoint was called.
        self::assertStringStartsWith('users?', $request->getUrl());
        // Verify that one email was requested, and is as expected.
        self::assertStringContainsString(
            'q=email:("some@mail.com")',
            urldecode($request->getParams())
        );
    }

    /**
     * Verifies that the underlying API is only called once for subsequent retrievals for the same email, multiple.
     */
    public function testGetByEmailsCaching()
    {
        // Response base on Auth0 API documentation
       Auth0::getSdk()->management()->getHttpClient()
            ->mockResponse(new Response(200, [], '[{"user_id":"auth0|507f1f77bcf86cd799439020",
        "email":"john.doe@gmail.com","email_verified":false,"username":"johndoe","phone_number":"+199999999999999",
        "phone_verified":false,"created_at":"","updated_at":"","identities":[{"connection":"Initial-Connection",
        "user_id":"507f1f77bcf86cd799439020","provider":"auth0","isSocial":false}],"app_metadata":{},"user_metadata":{},
        "picture":"","name":"","nickname":"","multifactor":[""],"last_ip":"","last_login":"","logins_count":0,
        "blocked":false,"given_name":"","family_name":""},{"user_id":"other_user",
        "email":"other@gmail.com","email_verified":false,"username":"other","phone_number":
        "+199999999999999","phone_verified":false,"created_at":"","updated_at":"","identities":[{"connection":
        "Initial-Connection","user_id":"507f1f77bcf86cd799439020","provider":"auth0","isSocial":false}],
        "app_metadata":{},"user_metadata":{},"picture":"","name":"","nickname":"","multifactor":[""],"last_ip":
        "","last_login":"","logins_count":0,"blocked":false,"given_name":"","family_name":""}]'));

        // Call function under test, twice.
        $users1 = $this->repo->getByIds(collect(['john.doe@gmail.com', 'other@gmail.com']));
        $users2 = $this->repo->getByIds(collect(['john.doe@gmail.com', 'other@gmail.com']));

        // Verify the same object is returned.
        self::assertEquals($users1, $users2);

        // Verify only 1 api call was made.
        self::assertCount(1, $this->httpClient->getRequests());
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
        Auth0::getSdk()->management()->getHttpClient()
            ->mockResponses([clone $response, clone $response]);

        // Set cache TTL to 10 seconds in the config, and create new repository using this cache value.
        Config::set('mrd-auth0.cache_ttl', 10);
        $this->resetRepo();

        // First execute function under test twice, without delay.
        $this->repo->getByIds(collect(['john.doe@gmail.com', 'other@gmail.com']));
        $this->repo->getByIds(collect(['john.doe@gmail.com', 'other@gmail.com']));

        // Verify only 1 api call was made.
        self::assertCount(1, $this->httpClient->getRequests());

        // Increment time such that cache TTL should have passed.
        Carbon::setTestNow(Carbon::now()->addSeconds(11));

        // Execute function under test again, and expect a new API call to be made.
        $this->repo->getByIds(collect(['john.doe@gmail.com', 'other@gmail.com']));

        // Verify now a total of two api calls was made.
        self::assertCount(2, $this->httpClient->getRequests());
    }

    /**
     * Verifies that deletion works correctly
     */
    public function testDeleteOne()
    {
        // Altered response from Auth0 API documentation
        Auth0::getSdk()->management()->getHttpClient()
            ->mockResponse(new Response(204, [], ''));

        // Call function under test
        $userID = 'test';
        $this->repo->delete($userID);

        // Find the request that was sent to Auth0
        $request = Auth0::getSdk()->management()->getHttpClient()->getLastRequest();

        // Expect a Delete request
        self::assertEquals("DELETE", $request->getLastRequest()->getMethod());

        // Expect 1 api call
        self::assertCount(1, $this->httpClient->getRequests());

        // Verify correct endpoint was called
        self::assertEquals('users/' . $userID, $request->getUrl());
    }

    /**
     * Verifies that user creation works correctly
     */
    public function testCreateOne()
    {
        // response based on Auth0 documentation
        Auth0::getSdk()->management()->getHttpClient()
            ->mockResponse(new Response(201, [], '{"user_id":"auth0|507f1f77bcf86cd799439020",
        "email":"john.doe@gmail.com","email_verified":false,"username":"johndoe","phone_number":"+199999999999999",
        "phone_verified":false,"created_at":"","updated_at":"","identities":[{"connection":"Initial-Connection",
        "user_id":"507f1f77bcf86cd799439020","provider":"auth0","isSocial":false}],"app_metadata":{},"user_metadata":{},
        "picture":"","name":"","nickname":"","multifactor":[""],"last_ip":"","last_login":"","logins_count":0,
        "blocked":false,"given_name":"John","family_name":"Doe"}'));

        // call function under test
        $user = $this->repo->createUser("john.doe@gmail.com", "John", "Doe");

        // assert returned userId is the same as in the response
        self::assertEquals("auth0|507f1f77bcf86cd799439020", $user->user_id);
        self::assertEquals("john.doe@gmail.com", $user->email);
        self::assertEquals("John", $user->given_name);
        self::assertEquals("Doe", $user->family_name);

        // Find the request that was sent to Auth0
        $request = Auth0::getSdk()->management()->getHttpClient()->getLastRequest();

        // Expect a Post request
        self::assertEquals("POST", $request->getLastRequest()->getMethod());

        // Expect 1 api call
        self::assertCount(1, $this->httpClient->getRequests());

        // Verify correct endpoint was called
        self::assertEquals('users', $request->getUrl());
    }

    /**
     * Verifies that the correct data is queried from the auth0 management API
     */
    public function testGetAllUsers()
    {
        Auth0::getSdk()->management()->getHttpClient()
            ->mockResponse(new Response(200, [], '{
        "start": 0, "limit": 50, "length": 2, "users": [{"user_id":"auth0|507f1f77bcf86cd799439020",
        "email":"john.doe@gmail.com","email_verified":false,"username":"johndoe","phone_number":"+199999999999999",
        "phone_verified":false,"created_at":"","updated_at":"","identities":[{"connection":"Initial-Connection",
        "user_id":"507f1f77bcf86cd799439020","provider":"auth0","isSocial":false}],"app_metadata":{},"user_metadata":{},
        "picture":"","name":"","nickname":"","multifactor":[""],"last_ip":"","last_login":"","logins_count":0,
        "blocked":false,"given_name":"","family_name":""},{"user_id":"other_user",
        "email":"other@gmail.com","email_verified":false,"username":"other","phone_number":
        "+199999999999999","phone_verified":false,"created_at":"","updated_at":"","identities":[{"connection":
        "Initial-Connection","user_id":"507f1f77bcf86cd799439020","provider":"auth0","isSocial":false}],
        "app_metadata":{},"user_metadata":{},"picture":"","name":"","nickname":"","multifactor":[""],"last_ip":
        "","last_login":"","logins_count":0,"blocked":false,"given_name":"","family_name":""}], "total": 2}'));

        // Call function under test.
        $users = $this->repo->getAllUsers();

        // Expect two users returned.
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
        self::assertCount(1, $this->httpClient->getRequests());

        // Find the request that was sent to Auth0
        $request = Auth0::getSdk()->management()->getHttpClient()->getLastRequest();

        // Verify correct endpoint was called.
        self::assertStringStartsWith('users?', $request->getUrl());
    }

    /**
     * Verifies that the `getAllUsers()` function requests multiple pages when applicable.
     */
    public function testGetAllUsersPaginated()
    {
        $response1 = new Response(200, [], '{
        "start": 0, "limit": 2, "length": 2, "users": [{"user_id":"auth0|507f1f77bcf86cd799439020",
        "email":"john.doe@gmail.com","email_verified":false,"username":"johndoe","phone_number":"+199999999999999",
        "phone_verified":false,"created_at":"","updated_at":"","identities":[{"connection":"Initial-Connection",
        "user_id":"507f1f77bcf86cd799439020","provider":"auth0","isSocial":false}],"app_metadata":{},"user_metadata":{},
        "picture":"","name":"","nickname":"","multifactor":[""],"last_ip":"","last_login":"","logins_count":0,
        "blocked":false,"given_name":"","family_name":""},{"user_id":"other_user",
        "email":"other@gmail.com","email_verified":false,"username":"other","phone_number":
        "+199999999999999","phone_verified":false,"created_at":"","updated_at":"","identities":[{"connection":
        "Initial-Connection","user_id":"507f1f77bcf86cd799439020","provider":"auth0","isSocial":false}],
        "app_metadata":{},"user_metadata":{},"picture":"","name":"","nickname":"","multifactor":[""],"last_ip":
        "","last_login":"","logins_count":0,"blocked":false,"given_name":"","family_name":""}], "total": 3}');
        $response2 = new Response(200, [], '{
        "start": 2, "limit": 2, "length": 1, "users": [{"user_id":"third_user",
        "email":"three@gmail.com","email_verified":false,"username":"user3","phone_number":
        "+199999999999999","phone_verified":false,"created_at":"","updated_at":"","identities":[{"connection":
        "Initial-Connection","user_id":"507f1f77bcf86cd799439022","provider":"auth0","isSocial":false}],
        "app_metadata":{},"user_metadata":{},"picture":"","name":"","nickname":"","multifactor":[""],"last_ip":
        "","last_login":"","logins_count":0,"blocked":false,"given_name":"","family_name":""}], "total": 3}');

        Auth0::getSdk()->management()->getHttpClient()
            ->mockResponses([$response1, $response2]);

        // Call function under test.
        $users = $this->repo->getAllUsers();

        // Expect three users returned.
        self::assertEquals(3, $users->count());

        // Verify keyed by user id.
        self::assertContains('auth0|507f1f77bcf86cd799439020', $users->keys());
        self::assertContains('other_user', $users->keys());
        self::assertContains('third_user', $users->keys());

        // Verify the three users
        $user1 = $users->get('auth0|507f1f77bcf86cd799439020');
        self::assertNotNull($user1);
        self::assertEquals('johndoe', $user1->username);

        $user2 = $users->get('other_user');
        self::assertNotNull($user2);
        self::assertEquals('other', $user2->username);

        $user3 = $users->get('third_user');
        self::assertNotNull($user3);
        self::assertEquals('user3', $user3->username);

        // Expect 2 api calls.
        self::assertCount(2, $this->httpClient->getRequests());

        // Find the requests that were sent to Auth0.
        $request1 = $this->httpClient->getRequests()[0];
        $request2 = $this->httpClient->getRequests()[1];

        // Verify correct endpoints were called and include_totals=true was included in requests.
        foreach ([$request1, $request2] as $request) {
            self::assertEquals('/api/v2/users', $request2->getUri()->getPath());
            self::assertTrue(str_contains(urldecode($request->getUri()->getQuery()), 'include_totals=true'));
        }

        // Verify correct pages were included in the requests.
        self::assertTrue(str_contains(urldecode($request1->getUri()->getQuery()), 'page=0'));
        self::assertTrue(str_contains(urldecode($request2->getUri()->getQuery()), 'page=1'));
    }

    /**
     * Verifies that the underlying API is only called once for subsequent retrievals of all users.
     */
    public function testGetAllUsersCaching()
    {
        // Response base on Auth0 API documentation
        Auth0::getSdk()->management()->getHttpClient()
            ->mockResponse(new Response(200, [], '{
        "start": 0, "limit": 50, "length": 2, "users": [{"user_id":"auth0|507f1f77bcf86cd799439020",
        "email":"john.doe@gmail.com","email_verified":false,"username":"johndoe","phone_number":"+199999999999999",
        "phone_verified":false,"created_at":"","updated_at":"","identities":[{"connection":"Initial-Connection",
        "user_id":"507f1f77bcf86cd799439020","provider":"auth0","isSocial":false}],"app_metadata":{},"user_metadata":{},
        "picture":"","name":"","nickname":"","multifactor":[""],"last_ip":"","last_login":"","logins_count":0,
        "blocked":false,"given_name":"","family_name":""},{"user_id":"other_user",
        "email":"other@gmail.com","email_verified":false,"username":"other","phone_number":
        "+199999999999999","phone_verified":false,"created_at":"","updated_at":"","identities":[{"connection":
        "Initial-Connection","user_id":"507f1f77bcf86cd799439020","provider":"auth0","isSocial":false}],
        "app_metadata":{},"user_metadata":{},"picture":"","name":"","nickname":"","multifactor":[""],"last_ip":
        "","last_login":"","logins_count":0,"blocked":false,"given_name":"","family_name":""}], "total": 2}'));

        // Call function under test, twice.
        $users1 = $this->repo->getAllUsers();
        $users2 = $this->repo->getAllUsers();

        // Verify the same object is returned.
        self::assertEquals($users1, $users2);

        // Verify only 1 api call was made.
        self::assertCount(1, $this->httpClient->getRequests());
    }

    /**
     * Verifies that the chunk size can be set using a config value for get multiple users by ID.
     * Does not verify response.
     */
    public function testGetByIdsConfigurableChunkSize()
    {
        // Response based on Auth0 API documentation
        $response1 = new Response(200, [], '[{"user_id":"auth0|507f1f77bcf86cd799439020",
        "email":"john.doe@gmail.com","email_verified":false,"username":"johndoe","phone_number":"+199999999999999",
        "phone_verified":false,"created_at":"","updated_at":"","identities":[{"connection":"Initial-Connection",
        "user_id":"507f1f77bcf86cd799439020","provider":"auth0","isSocial":false}],"app_metadata":{},"user_metadata":{},
        "picture":"","name":"","nickname":"","multifactor":[""],"last_ip":"","last_login":"","logins_count":0,
        "blocked":false,"given_name":"","family_name":""}]');
        $response2 = new Response(200, [], '[{"user_id":"other_user",
        "email":"other@gmail.com","email_verified":false,"username":"other","phone_number":
        "+199999999999999","phone_verified":false,"created_at":"","updated_at":"","identities":[{"connection":
        "Initial-Connection","user_id":"507f1f77bcf86cd799439020","provider":"auth0","isSocial":false}],
        "app_metadata":{},"user_metadata":{},"picture":"","name":"","nickname":"","multifactor":[""],"last_ip":
        "","last_login":"","logins_count":0,"blocked":false,"given_name":"","family_name":""}]');
        $response3 = new Response(200, [], '[{"user_id":"auth0|507f1f77bcf86cd799439020",
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

        Auth0::getSdk()->management()->getHttpClient()
            ->mockResponses([$response1, $response2, $response3]);

        // Set chunk size to 1 in the config, and create new repository using this chunk size.
        Config::set('mrd-auth0.chunk_size', 1);
        $this->resetRepo();

        // Request two users.
        $this->repo->getByIds(collect(['auth0|507f1f77bcf86cd799439020', 'other_user']));
        // Flush cache such that it does not interfere with this test.
        Cache::flush();

        // Verify 2 api calls were made.
        self::assertCount(2, $this->httpClient->getRequests());

        // Set chunk size to 2 in the config, and create new repository using this chunk size.
        Config::set('mrd-auth0.chunk_size', 2);
        $this->resetRepo();

        // Execute function under test again, and expect only 1 api call to be made in this case (so total of 3).
        $this->repo->getByIds(collect(['auth0|507f1f77bcf86cd799439020', 'other_user']));
        self::assertCount(3, $this->httpClient->getRequests());
    }

    /**
     * Verifies that retrieving by ID is chunked when requesting more users than the configured chunk size.
     */
    public function testGetByIdChunking()
    {
        // Responses based on Auth0 API documentation
        $response1 = new Response(200, [], '[{"user_id":"auth0|507f1f77bcf86cd799439020",
        "email":"john.doe@gmail.com","email_verified":false,"username":"johndoe","phone_number":"+199999999999999",
        "phone_verified":false,"created_at":"","updated_at":"","identities":[{"connection":"Initial-Connection",
        "user_id":"507f1f77bcf86cd799439020","provider":"auth0","isSocial":false}],"app_metadata":{},"user_metadata":{},
        "picture":"","name":"","nickname":"","multifactor":[""],"last_ip":"","last_login":"","logins_count":0,
        "blocked":false,"given_name":"","family_name":""},{"user_id":"other_user",
        "email":"other@gmail.com","email_verified":false,"username":"other","phone_number":
        "+199999999999999","phone_verified":false,"created_at":"","updated_at":"","identities":[{"connection":
        "Initial-Connection","user_id":"507f1f77bcf86cd799439021","provider":"auth0","isSocial":false}],
        "app_metadata":{},"user_metadata":{},"picture":"","name":"","nickname":"","multifactor":[""],"last_ip":
        "","last_login":"","logins_count":0,"blocked":false,"given_name":"","family_name":""}]');
        $response2 = new Response(200, [], '[{"user_id":"third_user",
        "email":"three@gmail.com","email_verified":false,"username":"user3","phone_number":
        "+199999999999999","phone_verified":false,"created_at":"","updated_at":"","identities":[{"connection":
        "Initial-Connection","user_id":"507f1f77bcf86cd799439022","provider":"auth0","isSocial":false}],
        "app_metadata":{},"user_metadata":{},"picture":"","name":"","nickname":"","multifactor":[""],"last_ip":
        "","last_login":"","logins_count":0,"blocked":false,"given_name":"","family_name":""}]');

        Auth0::getSdk()->management()->getHttpClient()
            ->mockResponses([$response1, $response2]);

        // Set chunk size to 2 in the config, and create new repository using this chunk size.
        Config::set('mrd-auth0.chunk_size', 2);
        $this->resetRepo();

        // Call function under test.
        $users = $this->repo->getByIds(collect(['auth0|507f1f77bcf86cd799439020', 'other_user', 'third_user']));

        // Expect three users returned.
        self::assertEquals(3, $users->count());
        // Verify keyed by user id.
        self::assertContains('auth0|507f1f77bcf86cd799439020', $users->keys());
        self::assertContains('other_user', $users->keys());
        self::assertContains('third_user', $users->keys());

        // Verify three users
        $user1 = $users->get('auth0|507f1f77bcf86cd799439020');
        self::assertNotNull($user1);
        self::assertEquals('johndoe', $user1->username);

        $user2 = $users->get('other_user');
        self::assertNotNull($user2);
        self::assertEquals('other', $user2->username);

        $user3 = $users->get('third_user');
        self::assertNotNull($user3);
        self::assertEquals('user3', $user3->username);

        // Expect 2 api calls.
        self::assertCount(2, $this->httpClient->getRequests());

        // Find the request that was sent to Auth0
        $request1 = $this->httpClient->getRequests()[0];
        $request2 = $this->httpClient->getRequests()[1];
        $query1 = urldecode($request1->getUri()->getQuery());
        $query2 = urldecode($request2->getUri()->getQuery());

        // Verify correct endpoint was called.
        self::assertEquals('/api/v2/users', $request1->getUri()->getPath());
        self::assertEquals('/api/v2/users', $request2->getUri()->getPath());
        // Verify correct query sent to Auth0. Order of IDs does not matter.
        self::assertTrue(
            str_contains($query1, 'q=user_id:("auth0|507f1f77bcf86cd799439020" OR "other_user")') ||
            str_contains($query1, 'q=user_id:("other_user" OR "auth0|507f1f77bcf86cd799439020")'),
            $query1
        );
        self::assertTrue(
            str_contains($query2, 'q=user_id:("third_user")'),
            $query2
        );
    }

    /**
     * Verifies that the chunk size can be set using a config value for get multiple users by email.
     * Does not verify response.
     */
    public function testGetByEmailsConfigurableChunkSize()
    {
        // Response based on Auth0 API documentation
        $response1 = new Response(200, [], '[{"user_id":"auth0|507f1f77bcf86cd799439020",
        "email":"john.doe@gmail.com","email_verified":false,"username":"johndoe","phone_number":"+199999999999999",
        "phone_verified":false,"created_at":"","updated_at":"","identities":[{"connection":"Initial-Connection",
        "user_id":"507f1f77bcf86cd799439020","provider":"auth0","isSocial":false}],"app_metadata":{},"user_metadata":{},
        "picture":"","name":"","nickname":"","multifactor":[""],"last_ip":"","last_login":"","logins_count":0,
        "blocked":false,"given_name":"","family_name":""}]');
        $response2 = new Response(200, [], '[{"user_id":"other_user",
        "email":"other@gmail.com","email_verified":false,"username":"other","phone_number":
        "+199999999999999","phone_verified":false,"created_at":"","updated_at":"","identities":[{"connection":
        "Initial-Connection","user_id":"507f1f77bcf86cd799439020","provider":"auth0","isSocial":false}],
        "app_metadata":{},"user_metadata":{},"picture":"","name":"","nickname":"","multifactor":[""],"last_ip":
        "","last_login":"","logins_count":0,"blocked":false,"given_name":"","family_name":""}]');
        $response3 = new Response(200, [], '[{"user_id":"auth0|507f1f77bcf86cd799439020",
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

        Auth0::getSdk()->management()->getHttpClient()
            ->mockResponses([$response1, $response2, $response3]);

        // Set chunk size to 1 in the config, and create new repository using this chunk size.
        Config::set('mrd-auth0.chunk_size', 1);
        $this->resetRepo();

        // Request two users.
        $this->repo->getByEmails(collect(['john.doe@gmail.com', 'other@gmail.com']));
        // Flush cache such that it does not interfere with this test.
        Cache::flush();

        // Verify 2 api calls were made.
        self::assertCount(2, $this->httpClient->getRequests());

        // Set chunk size to 2 in the config, and create new repository using this chunk size.
        Config::set('mrd-auth0.chunk_size', 2);
        $this->resetRepo();

        // Execute function under test again, and expect only 1 api call to be made in this case (so total of 3).
        $this->repo->getByIds(collect(['john.doe@gmail.com', 'other@gmail.com']));
        self::assertCount(3, $this->httpClient->getRequests());
    }

    /**
     * Verifies that retrieving by email is chunked when requesting more users than the configured chunk size.
     */
    public function testGetByEmailsChunking()
    {
        // Responses based on Auth0 API documentation
        $response1 = new Response(200, [], '[{"user_id":"auth0|507f1f77bcf86cd799439020",
        "email":"john.doe@gmail.com","email_verified":false,"username":"johndoe","phone_number":"+199999999999999",
        "phone_verified":false,"created_at":"","updated_at":"","identities":[{"connection":"Initial-Connection",
        "user_id":"507f1f77bcf86cd799439020","provider":"auth0","isSocial":false}],"app_metadata":{},"user_metadata":{},
        "picture":"","name":"","nickname":"","multifactor":[""],"last_ip":"","last_login":"","logins_count":0,
        "blocked":false,"given_name":"","family_name":""},{"user_id":"other_user",
        "email":"other@gmail.com","email_verified":false,"username":"other","phone_number":
        "+199999999999999","phone_verified":false,"created_at":"","updated_at":"","identities":[{"connection":
        "Initial-Connection","user_id":"507f1f77bcf86cd799439021","provider":"auth0","isSocial":false}],
        "app_metadata":{},"user_metadata":{},"picture":"","name":"","nickname":"","multifactor":[""],"last_ip":
        "","last_login":"","logins_count":0,"blocked":false,"given_name":"","family_name":""}]');
        $response2 = new Response(200, [], '[{"user_id":"third_user",
        "email":"three@gmail.com","email_verified":false,"username":"user3","phone_number":
        "+199999999999999","phone_verified":false,"created_at":"","updated_at":"","identities":[{"connection":
        "Initial-Connection","user_id":"507f1f77bcf86cd799439022","provider":"auth0","isSocial":false}],
        "app_metadata":{},"user_metadata":{},"picture":"","name":"","nickname":"","multifactor":[""],"last_ip":
        "","last_login":"","logins_count":0,"blocked":false,"given_name":"","family_name":""}]');

        Auth0::getSdk()->management()->getHttpClient()
            ->mockResponses([$response1, $response2]);

        // Set chunk size to 2 in the config, and create new repository using this chunk size.
        Config::set('mrd-auth0.chunk_size', 2);
        $this->resetRepo();

        // Call function under test.
        $users = $this->repo->getByEmails(collect(['john.doe@gmail.com', 'other@gmail.com', 'three@gmail.com']));

        // Expect three users returned.
        self::assertEquals(3, $users->count());
        // Verify keyed by email.
        self::assertContains('john.doe@gmail.com', $users->keys());
        self::assertContains('other@gmail.com', $users->keys());
        self::assertContains('three@gmail.com', $users->keys());

        // Verify three users
        $user1 = $users->get('john.doe@gmail.com');
        self::assertNotNull($user1);
        self::assertEquals('johndoe', $user1->username);

        $user2 = $users->get('other@gmail.com');
        self::assertNotNull($user2);
        self::assertEquals('other', $user2->username);

        $user3 = $users->get('three@gmail.com');
        self::assertNotNull($user3);
        self::assertEquals('user3', $user3->username);

        // Expect 2 api calls.
        self::assertCount(2, $this->httpClient->getRequests());

        // Find the request that was sent to Auth0
        $request1 = $this->httpClient->getRequests()[0];
        $request2 = $this->httpClient->getRequests()[1];
        $query1 = urldecode($request1->getUri()->getQuery());
        $query2 = urldecode($request2->getUri()->getQuery());

        // Verify correct endpoint was called.
        self::assertEquals('/api/v2/users', $request1->getUri()->getPath());
        self::assertEquals('/api/v2/users', $request2->getUri()->getPath());
        // Verify correct query sent to Auth0. Order of IDs does not matter.
        self::assertTrue(
            str_contains($query1, 'q=email:("john.doe@gmail.com" OR "other@gmail.com")') ||
            str_contains($query1, 'q=user_id:("other@gmail.com" OR "john.doe@gmail.com")'),
            $query1
        );
        self::assertTrue(
            str_contains($query2, 'q=email:("three@gmail.com")'),
            $query2
        );
    }
}
