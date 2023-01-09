<?php


namespace Marketredesign\MrdAuth0Laravel\Tests\Feature;

use Auth0\Laravel\Facade\Auth0;
use Auth0\Laravel\Store\LaravelSession;
use Auth0\SDK\Contract\Auth0Interface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redirect;
use Marketredesign\MrdAuth0Laravel\Tests\TestCase;
use Mockery;

class LoginTest extends TestCase
{
    private const ROUTE_NAME = 'login';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('auth0', [
            'strategy' => 'webapp',
            'domain'   => 'auth.marketredesign.com',
            'audience' => ['https://api.pricecypher.com'],
            'redirectUri' => 'https://redirect.com/oauth/callback',
            'sessionStorage' => new LaravelSession(),
            'transientStorage' => new LaravelSession(),
            'clientId' => '123',
            'cookieSecret' => 'abc',
        ]);

        $this->resetAuth0Config();
    }

    /**
     * Verifies that the user is redirected back when already logged in.
     */
    public function testAlreadyLoggedIn()
    {
        Config::set('auth0.routes.home', '/some-home-url');

        // Verify we are redirected to the configured home URL.
        $this->actingAsAuth0User();
        // Sanity check; make sure a user is logged in.
        self::assertTrue(Auth::check());
        $this->get(route(self::ROUTE_NAME))->assertRedirect('/some-home-url');

        // Now set an intended URL in the user session and verify we are redirected there instead.
        Redirect::setIntendedUrl('/something');
        $this->actingAsAuth0User();
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

        // Mock out the Auth0 SDK, and expect 'getCredentials' and 'login' methods to be called exactly once.
        $sdkMock = Mockery::mock(Auth0Interface::class)
            ->shouldReceive([
                'getCredentials' => null,
                'login' => 'https://login.com',
            ])
            ->once()
            ->getMock();
        // Make sure the mocked SDK is used by the Auth0 Facade.
        Auth0::setSdk($sdkMock);

        // Expect redirect away to login.com
        $this->get(route(self::ROUTE_NAME))->assertRedirect('https://login.com');
    }
}
