<?php


namespace Marketredesign\MrdAuth0Laravel\Tests\Feature;

use Auth0\Login\Auth0User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Marketredesign\MrdAuth0Laravel\Tests\TestCase;

class PermissionAuthorizationTest extends TestCase
{
    private const ROUTE_URI = 'test_route';

    /**
     * Perform a GET request to the test endpoint, which responds with a JSON with 'test_response' if successful.
     *
     * @param string $permission Optionally, a required permission for the CheckPermission middleware. Defaults to none.
     * @return TestResponse
     */
    private function request(string $permission = '')
    {
        // Define a very simple testing endpoint, protected by the permission middleware.
        Route::middleware('permission' . (empty($permission) ? '' : ":$permission"))
            ->get(self::ROUTE_URI, function () {
                    return response()->json('test_response');
                });

        return $this->getJson(self::ROUTE_URI);
    }

    /**
     * Verifies that the user is redirected to the login page when not already logged in in the case where no specific
     * permission is required.
     */
    public function testNotLoggedInNoPermRequested()
    {
        // Sanity check; make sure no user is logged in.
        self::assertFalse(Auth::check());

        // Expect redirect to the login endpoint.
        $this->request()->assertRedirect('login');
    }

    /**
     * Verifies that the user is redirected to the login page when not already logged in in the case where some specific
     * permission is required.
     */
    public function testNotLoggedInPermRequested()
    {
        // Sanity check; make sure no user is logged in.
        self::assertFalse(Auth::check());

        // Expect redirect to the login endpoint.
        $this->request('read:test')->assertRedirect('login');
    }

    /**
     * Verifies that the user is allowed access when the user is logged in and no specific permissions are required.
     */
    public function testLoggedInNoPermRequested()
    {
        // Login as some user.
        $this->be(new Auth0User([], 'anAccessToken'));

        // Sanity check; make sure a user is logged in.
        self::assertTrue(Auth::check());

        // Mock out the Auth0Service s.t. when the JWT is attempted to be decoded, it results in these permissions
        $this->mockAuth0Service(['permissions' => ['read:test']]);

        // Verify the user is allowed access
        $this->request()->assertOk();
    }

    /**
     * Verifies that the user is refused access when the user is logged in but a permission is required while the user
     * has no permissions at all.
     */
    public function testLoggedInPermRequestedNonePresent()
    {
        // Login as some user.
        $this->be(new Auth0User([], 'anAccessToken'));

        // Sanity check; make sure a user is logged in.
        self::assertTrue(Auth::check());

        // Mock out the Auth0Service s.t. when the JWT is attempted to be decoded, it results in these permissions
        $this->mockAuth0Service(['permissions' => []]);

        // Verify that the user is refused access because of insufficient permissions
        $this->request('read:test')->assertUnauthorized()->assertSee('Insufficient permissions');
    }

    /**
     * Verifies that the user is refused access when the user is logged in but a permission is required that the user
     * does not have, while they do have other permissions.
     */
    public function testLoggedInPermRequestedWrongPresent()
    {
        // Login as some user.
        $this->be(new Auth0User([], 'anAccessToken'));

        // Sanity check; make sure a user is logged in.
        self::assertTrue(Auth::check());

        // Mock out the Auth0Service s.t. when the JWT is attempted to be decoded, it results in these permissions
        $this->mockAuth0Service(['permissions' => ['write:test', 'read:another']]);

        // Verify that the user is refused access because of insufficient permissions
        $this->request('read:test')->assertUnauthorized()->assertSee('Insufficient permissions');
    }

    /**
     * Verifies that the user is allowed access when the user is logged in and a specific permissions that the user
     * does have is required.
     */
    public function testLoggedInPermRequestedPresent()
    {
        // Login as some user.
        $this->be(new Auth0User([], 'anAccessToken'));

        // Sanity check; make sure a user is logged in.
        self::assertTrue(Auth::check());

        // Mock out the Auth0Service s.t. when the JWT is attempted to be decoded, it results in these permissions
        $this->mockAuth0Service(['permissions' => ['write:test', 'read:test']]);

        // Verify that the user is allowed access
        $this->request('read:test')->assertOk();
    }

    /**
     * Verifies that when the user does not have an access token attached, for example for a misconfigured login
     * process, the user is denied access.
     */
    public function testLoggedInNoAccessToken()
    {
        // Login as some user without an access token attached
        $this->be(new Auth0User([], null));

        // Sanity check; make sure a user is logged in without access token.
        self::assertTrue(Auth::check());
        self::assertNull(Auth::user()->getAuthPassword());

        // Mock out the Auth0Service s.t. when the JWT is attempted to be decoded, it results in these permissions
        $this->mockAuth0Service(['permissions' => ['read:test']]);

        // Verify that the user is not allowed access
        $this->request('read:test')->assertUnauthorized()->assertSee('No access token present');
    }
}
