<?php


namespace Marketredesign\MrdAuth0Laravel\Tests\Feature;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redirect;
use Marketredesign\MrdAuth0Laravel\Tests\TestCase;

class LogoutTest extends TestCase
{
    private const ROUTE_NAME = 'logout';

    /**
     * Define environment setup.
     *
     * @param  Application  $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        // Set the Laravel Auth0 config values which are used to some values.
        $app['config']->set('auth0', [
            'strategy' => 'webapp',
            'domain'     => 'auth.marketredesign.com',
            'audience' => ['https://api.pricecypher.com'],
            'clientId'  => '123',
        ]);
    }

    /**
     * Verifies that the user is redirected to Auth0's logout page when already logged in, and logged out afterwards.
     */
    public function testNormalLogout()
    {
        // Login as some user.
        $this->actingAsAuth0User();

        // Sanity check; make sure a user is logged in.
        self::assertTrue(Auth::check());

        // Assert we are redirected to Auth0 logout page.
        $this->get(route(self::ROUTE_NAME))->assertRedirect()
            ->assertRedirectContains('https://auth.marketredesign.com/v2/logout')
            ->assertRedirectContains('client_id=123')
            ->assertRedirectContains('returnTo=' . urlencode('http://localhost'));

        // Verify that the user is indeed logged out now.
        self::assertFalse(Auth::check());
    }

    /**
     * Verifies that the user is redirected to intended / home page when trying to logout.
     */
    public function testLogoutWithoutBeingLoggedIn()
    {
        Config::set('auth0.routes.home', '/some-home-url');

        // Sanity check; make sure no user is logged in.
        self::assertFalse(Auth::check());

        // Assert we are redirected to the home page.
        $this->get(route(self::ROUTE_NAME))->assertRedirect('/some-home-url');

        // Verify that no user was magically logged in.
        self::assertFalse(Auth::check());

        // Now set an intended URL and verify we are redirected there instead.
        Redirect::setIntendedUrl('/something');
        $this->get(route(self::ROUTE_NAME))->assertRedirect('/something');
    }
}
