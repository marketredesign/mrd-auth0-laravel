<?php


namespace Marketredesign\MrdAuth0Laravel\Tests\Feature;

use Auth0\Laravel\Facade\Auth0;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redirect;
use Marketredesign\MrdAuth0Laravel\Tests\TestCase;
use Mockery;

class LoginTest extends TestCase
{
    private const ROUTE_NAME = 'login';

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
     * Verifies that the user is redirected back when already logged in.
     */
    public function testAlreadyLoggedIn()
    {
        Config::set('auth0.routes.home', '/some-home-url');

        // Login as some user.
        $this->actingAsAuth0User();

        // Sanity check; make sure a user is logged in.
        self::assertTrue(Auth::check());

        // Verify we are redirected to the configured home URL.
        $this->get(route(self::ROUTE_NAME))->assertRedirect('/some-home-url');

        // Now set an intended URL in the user session and verify we are redirected there instead.
        Redirect::setIntendedUrl('/something');
        $this->get(route(self::ROUTE_NAME))->assertRedirect('/something');
    }

    /**
     * Verifies that the user is redirected to Auth0 when not already logged in.
     */
    public function testNotLoggedIn()
    {
        // Sanity check; make sure no user is logged in.
        self::assertFalse(Auth::check());

        // Mock out the Auth0 SDK, and expect 'login' method to be called exactly once.
        $sdkMock = Mockery::mock(Auth0::getSdk())
            ->shouldReceive('login')
            ->once()
            ->andReturn('https://login.com')
            ->getMock();
        // Make sure the mocked SDK is used by the Auth0 Facade.
        $this->mock('auth0')
            ->shouldReceive('getSdk')
            ->andReturn($sdkMock)
            ->getMock();

        // Expect redirect away to login.com
        $this->get(route(self::ROUTE_NAME))->assertRedirect('https://login.com');
    }
}
