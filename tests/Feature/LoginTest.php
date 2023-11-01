<?php


namespace Marketredesign\MrdAuth0Laravel\Tests\Feature;

use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redirect;
use Marketredesign\MrdAuth0Laravel\Tests\TestCase;

class LoginTest extends TestCase
{
    private const ROUTE_NAME = 'oidc-login';

    protected $guard = 'pc-oidc';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('pricecypher-oidc.routes.home', 'https://pricecypher.com/home/route');

        request()->setLaravelSession(app(Session::class));
    }

    /**
     * Verifies that the user is redirected back when already logged in.
     */
    public function testAlreadyLoggedIn()
    {
        $this->auth([], false);
        // Sanity check; make sure a user is logged in.
        self::assertTrue(Auth::check());

        // Verify we are redirected to the configured home URL.
        $this->get(route(self::ROUTE_NAME))->assertRedirect('https://pricecypher.com/home/route');

        // Now set an intended URL in the user session and verify we are redirected there instead.
        Redirect::setIntendedUrl('/something');
        $this->auth([], false);
        // Sanity check; make sure a user is logged in.
        self::assertTrue(Auth::check());
        $this->get(route(self::ROUTE_NAME))->assertRedirect('/something');
    }

    /**
     * Verifies that the user is redirected to Auth0 when not already logged in.
     */
    public function testNotLoggedIn()
    {
        // Sanity check; make sure no user is logged in.
        self::assertFalse(Auth::check());

        $callback = urlencode(route('oidc-callback'));

        $this->get(route(self::ROUTE_NAME))
            ->assertRedirectContains('https://domain.test/authorize')
            ->assertRedirectContains('scope=openid')
            ->assertRedirectContains("redirect_uri=$callback");
    }
}
