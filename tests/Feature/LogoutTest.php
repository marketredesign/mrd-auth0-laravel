<?php


namespace Marketredesign\MrdAuth0Laravel\Tests\Feature;

use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redirect;
use Marketredesign\MrdAuth0Laravel\Tests\TestCase;

class LogoutTest extends TestCase
{
    private const ROUTE_NAME = 'oidc-logout';

    protected $guard = 'pc-oidc';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('pricecypher-oidc.logout_endpoint', 'https://domain.test/oidc/logout');
        request()->setLaravelSession(app(Session::class));
    }

    /**
     * Verifies that the user is redirected to Auth0's logout page when already logged in, and logged out afterward.
     */
    public function testNormalLogout()
    {
        // Login as some user.
        $this->auth([], false);

        // Sanity check; make sure a user is logged in.
        self::assertTrue(Auth::guard($this->guard)->check());

        // Assert we are redirected to Auth0 logout page.
        $this->get(route(self::ROUTE_NAME))->assertRedirect()
            ->assertRedirectContains('https://domain.test/oidc/logout')
            ->assertRedirectContains('client_id=id')
            ->assertRedirectContains('post_logout_redirect_uri=' . urlencode('http://localhost'));

        // Verify that the user is indeed logged out now.
        self::assertFalse(Auth::guard($this->guard)->check());
    }

    /**
     * Verifies that the user is redirected to intended / home page when trying to logout.
     */
    public function testLogoutWithoutBeingLoggedIn()
    {
        Config::set('pricecypher-oidc.routes.home', '/some-home-url');

        // Sanity check; make sure no user is logged in.
        self::assertFalse(Auth::guard($this->guard)->check());

        // Assert we are redirected to the home page.
        $this->get(route(self::ROUTE_NAME))->assertRedirect('/some-home-url');

        // Verify that no user was magically logged in.
        self::assertFalse(Auth::guard($this->guard)->check());

        // Now set an intended URL and verify we are redirected there instead.
        Redirect::setIntendedUrl('/something');
        $this->get(route(self::ROUTE_NAME))->assertRedirect('/something');
    }
}
