<?php


namespace Marketredesign\MrdAuth0Laravel\Tests\Feature;

use Auth0\Login\Auth0Service;
use Auth0\Login\Auth0User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Marketredesign\MrdAuth0Laravel\Tests\TestCase;

class LoginTest extends TestCase
{
    private const ROUTE_NAME = 'login';

    /**
     * Verifies that the user is redirected back when already logged in.
     */
    public function testAlreadyLoggedIn()
    {
        // Login as some user.
        $this->be(new Auth0User([], null));

        // Sanity check; make sure a user is logged in.
        self::assertTrue(Auth::check());

        // Mock out the Auth0Service, and expect 'login' method not to be called.
        $auth0Mock = $this->mock(Auth0Service::class)->shouldNotReceive('login')->getMock();
        $this->app->instance(Auth0Service::class, $auth0Mock);

        // Set referer to verify we are indeed redirected 'back'.
        $this->get(route(self::ROUTE_NAME), [
            'HTTP_REFERER' => '/something'
        ])->assertRedirect('/something');
    }

    /**
     * Verifies that the user is redirected to Auth0 when not already logged in.
     */
    public function testNotLoggedIn()
    {
        // Sanity check; make sure no user is logged in.
        self::assertFalse(Auth::check());

        // Mock out the Auth0Service, and expect 'login' method to be called exactly once.
        $auth0Mock = $this->mock(Auth0Service::class)
            ->shouldReceive('login')
            ->once()
            ->andReturn(Redirect::to('auth0'))
            ->getMock();
        $this->app->instance(Auth0Service::class, $auth0Mock);

        // Expect redirect to 'auth0'.
        $this->get(route(self::ROUTE_NAME), [
            'HTTP_REFERER' => '/something'
        ])->assertRedirect('auth0');
    }
}
