<?php


namespace Marketredesign\MrdAuth0Laravel\Tests\Feature;

use Auth0\SDK\Configuration\SdkConfiguration;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Marketredesign\MrdAuth0Laravel\Tests\TestCase;

class JwtAuthorizationTest extends TestCase
{
    private const ROUTE_URI = 'test_route';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('auth0', [
            'strategy' => SdkConfiguration::STRATEGY_API,
            'domain'   => 'auth.marketredesign.com',
            'audience' => ['https://api.pricecypher.com'],
            'clientId' => '123',
        ]);

        $this->resetAuth0Config();
    }

    /**
     * Perform a GET request to the test endpoint, optionally including a Bearer token in the Authorization header.
     *
     * @param bool $includeBearer Whether or not to include a bearer token in the request.
     * @param string $scope Optionally, a required scope for the JWT middleware. Defaults to no scope.
     * @param Closure|null $responseHandler Response handler for endpoint. When null, a JSON containing `test` will be
     * returned.
     * @return TestResponse
     */
    private function request(bool $includeBearer, string $scope = '', ?Closure $responseHandler = null)
    {
        // Define a very simple testing endpoint, protected by the jwt middleware.
        Route::middleware(['api', 'auth0.authorize' . (empty($scope) ? '' : ":$scope")])
            ->get(self::ROUTE_URI, $responseHandler ?? function () {
                return response()->json('test_response');
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
        $this->request(false)
            ->assertUnauthorized()
            ->assertSee('Unauthorized');
    }

    /**
     * Verify the request is unauthorized when the bearer token is invalid.
     */
    public function testInvalidToken()
    {
        $this->request(true)->assertUnauthorized();
    }

    /**
     * Verify the request is forbidden when the bearer token contains no scope, but a scope is required.
     */
    public function testScopeRequiredNoneProvided()
    {
        $this->request(true, 'test_scope')
            ->assertUnauthorized()
            ->assertSee('Unauthorized');
    }

    /**
     * Verify the request is forbidden when the bearer token contains an empty scope, but a scope is required.
     */
    public function testScopeRequiredEmptyScopesProvided()
    {
        $this->actingAsAuth0User(['scope' => '']);

        $this->request(true, 'test_scope')
            ->assertForbidden()
            ->assertSee('Forbidden');
    }

    /**
     * Verify the request is forbidden when the bearer token only has a different scope than required.
     */
    public function testScopeRequiredIncorrectScopeProvided()
    {
        $this->actingAsAuth0User(['scope' => 'nottest_scope']);

        $this->request(true, 'test_scope')
            ->assertForbidden()
            ->assertSee('Forbidden');
    }

    /**
     * Verify the request passes when it contains one scope only, which is required.
     */
    public function testScopeRequiredOneScopeProvided()
    {
        $this->actingAsAuth0User(['scope' => 'test_scope']);

        $this->request(true, 'test_scope')
            ->assertOk()
            ->assertSee('test_response');
    }

    /**
     * Verify the request passes when it contains multiple scopes including the one that is required.
     */
    public function testScopeRequiredMultipleScopesProvided()
    {
        $this->actingAsAuth0User(['scope' => 'somescope test_scope somethingelse']);

        $this->request(true, 'test_scope')
            ->assertOk()
            ->assertSee('test_response');
    }

    /**
     * Verify that the request user resolver returns a Auth0JWT user with correct userinfo.
     */
    public function testUserResolver()
    {
        // Create some user info as it would be returned from Auth0.
        $jwt = [
            'sub' => 'someuser',
        ];

        $this->actingAsAuth0User($jwt);

        $this->request(true, '', function (Request $request) {
            // Apply the user resolver on the request and return its response.
            return [
                'sub' => $request->user()->sub
            ];
        })->assertOk()->assertSimilarJson($jwt);
    }
}
