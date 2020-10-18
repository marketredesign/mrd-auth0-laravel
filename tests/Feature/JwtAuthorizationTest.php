<?php


namespace Marketredesign\MrdAuth0Laravel\Tests\Feature;

use Closure;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Marketredesign\MrdAuth0Laravel\Tests\TestCase;

class JwtAuthorizationTest extends TestCase
{
    private const ROUTE_URI = 'test';

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
     * Perform a GET request to the test endpoint, optionally including a Bearer token in the Authorization header.
     *
     * @param bool $includeBearer Whether or not to include a bearer token in the request.
     * @param Closure|null $responseHandler Response handler for endpoint. When null, a JSON containing `test` will be
     * returned.
     * @return TestResponse
     */
    private function request(bool $includeBearer, string $scope = '', ?Closure $responseHandler = null)
    {
        // Define a very simply testing endpoint, protected by the jwt middleware.
        Route::middleware('jwt' . (empty($scope) ? '' : ":$scope"))
            ->get(self::ROUTE_URI, $responseHandler ?? function () {
                return response()->json('test');
            });

        $headers = [];

        if ($includeBearer) {
            $headers['Authorization'] = 'Bearer token';
        }

        return $this->getJson(self::ROUTE_URI, $headers);
    }

    /**
     * Verify the request is unauthorized when no bearer token is specified.
     */
    public function testBearerTokenMissing()
    {
        $this->mockAuth0Service(null);

        $this->request(false)
            ->assertUnauthorized()
            ->assertSee('Bearer token missing');
    }

    /**
     * Verify the request is unauthorized when the bearer token is invalid.
     */
    public function testInvalidToken()
    {
        $this->mockAuth0Service(null);

        $this->request(true)->assertUnauthorized();
    }

    /**
     * Verify the request is forbidden when the bearer token contains no scope, but a scope is required.
     */
    public function testScopeRequiredNoneProvided()
    {
        $this->mockAuth0Service([]);

        $this->request(true, 'test')
            ->assertForbidden()
            ->assertSee('Insufficient scope');
    }

    /**
     * Verify the request is forbidden when the bearer token contains an empty scope, but a scope is required.
     */
    public function testScopeRequiredEmptyScopesProvided()
    {
        $this->mockAuth0Service(['scope' => '']);

        $this->request(true, 'test')
            ->assertForbidden()
            ->assertSee('Insufficient scope');
    }

    /**
     * Verify the request is forbidden when the bearer token only has a different scope than required.
     */
    public function testScopeRequiredIncorrectScopeProvided()
    {
        $this->mockAuth0Service(['scope' => 'nottest']);

        $this->request(true, 'test')
            ->assertForbidden()
            ->assertSee('Insufficient scope');
    }

    /**
     * Verify the request passes when it contains one scope only, which is required.
     */
    public function testScopeRequiredOneScopeProvided()
    {
        $this->mockAuth0Service(['scope' => 'test']);

        $this->request(true, 'test')
            ->assertOk()
            ->assertSee('test');
    }

    /**
     * Verify the request passes when it contains multiple scopes including the one that is required.
     */
    public function testScopeRequiredMultipleScopesProvided()
    {
        $this->mockAuth0Service(['scope' => 'somescope test somethingelse']);

        $this->request(true, 'test')
            ->assertOk()
            ->assertSee('test');
    }

    /**
     * Verify that the request user resolver returns a Auth0JWT user with correct userinfo.
     */
    public function testUserResolver()
    {
        // Create some user info as it would be returned from Auth0.
        $userInfo = [
            'sub' => 'someuser',
            'given_name' => 'Test',
            'family_name' => 'User',
            'nickname' => 'test.user',
            'name' => 'Test User',
            'email' => 'test.user@company.com',
        ];

        $this->mockAuth0Service(['sub' => 'someuser'], $userInfo);

        $this->request(true, '', function (Request $request) {
            // Apply the user resolver on the request and return its response.
            return $request->user();
        })->assertOk()->assertSee('test')->assertSimilarJson($userInfo);
    }
}
