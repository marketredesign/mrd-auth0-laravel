<?php


namespace Marketredesign\MrdAuth0Laravel\Tests\Feature;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Marketredesign\MrdAuth0Laravel\Tests\TestCase;

class PermissionAuthorizationTest extends TestCase
{
    private const ROUTE_URI = 'test_route';

    private string $permissionsClaim;

    protected function setUp(): void
    {
        parent::setUp();
        $this->permissionsClaim = config('pricecypher-oidc.permissions_claim');
    }

    private function authPermissions($permissions = [])
    {
        $jwt = [
            $this->permissionsClaim => $permissions,
        ];

        return $this->auth($jwt);
    }

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
     * Verifies that the user is redirected to the login page when not already logged in, in the case where no specific
     * permission is required.
     */
    public function testNotLoggedInNoPermRequested()
    {
        // Sanity check; make sure no user is logged in.
        self::assertFalse(Auth::check());

        // Expect redirect to the login endpoint.
        $this->request()->assertRedirectToRoute('oidc-login');
    }

    /**
     * Verifies that the user is redirected to the login page when not already logged in, in the case where some
     * specific permission is required.
     */
    public function testNotLoggedInPermRequested()
    {
        // Sanity check; make sure no user is logged in.
        self::assertFalse(Auth::check());

        // Expect redirect to the login endpoint.
        $this->request('read:test')->assertRedirectToRoute('oidc-login');
    }

    /**
     * Verifies that the user is allowed access when the user is logged in and no specific permissions are required.
     */
    public function testLoggedInNoPermRequested()
    {
        // Login as some user.
        $this->authPermissions();

        // Sanity check; make sure a user is logged in.
        self::assertTrue(Auth::check());

        // Verify the user is allowed access
        $this->request()->assertOk();
    }

    /**
     * Verifies authorization works properly, and a warning is logged, when permissions claim is missing from ID tokens.
     */
    public function testLoggedInNoPermissionsClaim()
    {
        // Sanity check; permissions is indeed not the permissions claim property name.
        self::assertNotEquals('permissions', $this->permissionsClaim);

        // First verify without permission checking, which should not result in any log messages.
        Log::shouldReceive('warning')->never();

        // Login as some user and add incorrect permissions claim to userinfo.
        $this->auth(['permissions' => ['read:test']]);
        // Sanity check; user should be logged in now.
        self::assertTrue(Auth::check());
        // Verify the user is allowed access.
        $this->request()->assertOk();

        // Next, do request permission checking, which should result in a log message.
        Log::shouldReceive('warning')->once()->withArgs(function ($message) {
            return strpos($message, 'permissions claim') !== false;
        });

        // Login as some user and add incorrect permissions claim to userinfo.
        $this->auth(['permissions' => ['read:test']]);
        // Sanity check; user should be logged in now.
        self::assertTrue(Auth::check());
        // Verify request is forbidden (permissions are not in correct place in ID token).
        $this->request('read:test')->assertForbidden()->assertSee('Insufficient permissions');
    }

    /**
     * Verifies that the user is refused access when the user is logged in but a permission is required while the user
     * has no permissions at all.
     */
    public function testLoggedInPermRequestedNonePresent()
    {
        // Login as some user.
        $this->authPermissions();

        // Sanity check; make sure a user is logged in.
        self::assertTrue(Auth::check());

        // Verify that the user is refused access because of insufficient permissions
        $this->request('read:test')->assertForbidden()->assertSee('Insufficient permissions');
    }

    /**
     * Verifies that the user is refused access when the user is logged in but a permission is required that the user
     * does not have, while they do have other permissions.
     */
    public function testLoggedInPermRequestedWrongPresent()
    {
        // Login as some user.
        $this->authPermissions(['write:test', 'read:another']);

        // Sanity check; make sure a user is logged in.
        self::assertTrue(Auth::check());

        // Verify that the user is refused access because of insufficient permissions
        $this->request('read:test')->assertForbidden()->assertSee('Insufficient permissions');
    }

    /**
     * Verifies that the user is allowed access when the user is logged in and a specific permissions that the user
     * does have is required.
     */
    public function testLoggedInPermRequestedPresent()
    {
        // Login as some user.
        $this->authPermissions(['write:test', 'read:test']);

        // Sanity check; make sure a user is logged in.
        self::assertTrue(Auth::check());

        // Verify that the user is allowed access
        $this->request('read:test')->assertOk();
    }

    /**
     * Verifies that the permissions claim key can be configured.
     */
    public function testPermissionsClaimConfigurable()
    {
        // Set some other permissions claim in the config.
        $otherClaim = 'some_other_permissions_claim';
        Config::set('pricecypher-oidc.permissions_claim', $otherClaim);

        // Verify it is indeed than the one used in the rest of the tests.
        self::assertNotEquals($this->permissionsClaim, $otherClaim);

        // Login as some user, and verify indeed logged in.
        $this->authPermissions(['write:test', 'read:another']);
        self::assertTrue(Auth::check());

        // Verify that the user is refused access because of insufficient permissions, even though the permissions
        // are present in the token using the previous claim.
        $this->request('write:test')->assertForbidden()->assertSee('Insufficient permissions');

        // Use the other claim in the tests as well now, login again, and verify that the request is allowed now.
        $this->permissionsClaim = $otherClaim;
        $this->authPermissions(['write:test', 'read:another']);
        self::assertTrue(Auth::check());

        // Verify that the request is authorized.
        $this->request('write:test')->assertOk();
    }
}
