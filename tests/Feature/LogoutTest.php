<?php


namespace Marketredesign\MrdAuth0Laravel\Tests\Feature;


use Auth0\Login\Auth0Service;
use Auth0\Login\Auth0User;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Marketredesign\MrdAuth0Laravel\Tests\TestCase;

class LogoutTest extends TestCase
{
    private const ROUTE_NAME = 'logout';

    protected function setUp(): void
    {
        parent::setUp();

        // Mock out the Auth0Service.
        $auth0Mock = $this->mock(Auth0Service::class);
        $this->app->instance(Auth0Service::class, $auth0Mock);
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
        ]);
    }

    /**
     * Verifies that the user is redirected to Auth0's logout page when already logged in, and logged out afterwards.
     */
    public function testNormalLogout()
    {
        // Login as some user.
        $this->be(new Auth0User([], null));

        // Sanity check; make sure a user is logged in.
        self::assertTrue(Auth::check());

        // Assert we are redirected to Auth0 logout page.
        $this->get(route(self::ROUTE_NAME))->assertRedirect(
            'https://auth.marketredesign.com/v2/logout?client_id=123&returnTo=http://localhost'
        );

        // Verify that the user is indeed logged out now.
        self::assertFalse(Auth::check());
    }

    /**
     * Verifies that the user is redirected to the login page when trying to logout. (Since this is a protected route).
     */
    public function testLogoutWithoutBeingLoggedIn()
    {
        // Sanity check; make sure no user is logged in.
        self::assertFalse(Auth::check());

        // Assert we are redirected to our login page.
        $this->get(route(self::ROUTE_NAME))->assertRedirect(route('login'));

        // Verify that no user was magically logged in.
        self::assertFalse(Auth::check());
    }
}
