<?php


namespace Marketredesign\MrdAuth0Laravel\Tests\Feature;

use Closure;
use Facile\JoseVerifier\TokenVerifierInterface;
use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use InvalidArgumentException;
use Marketredesign\MrdAuth0Laravel\Model\Stateless\User;
use Marketredesign\MrdAuth0Laravel\Tests\TestCase;
use Mockery\MockInterface;

class JwtAuthorizationTest extends TestCase
{
    private const ROUTE_URI = 'test_route';

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
        Route::middleware(['api', 'jwt' . (empty($scope) ? '' : ":$scope")])
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
        $this->auth(['scope' => ''])->request(true, 'test_scope')
            ->assertForbidden()
            ->assertSee('Forbidden');
    }

    /**
     * Verify the request is forbidden when the bearer token only has a different scope than required.
     */
    public function testScopeRequiredIncorrectScopeProvided()
    {
        $this->auth(['scope' => 'nottest_scope'])
            ->request(true, 'test_scope')
            ->assertForbidden()
            ->assertSee('Forbidden');
    }

    /**
     * Verify the request passes when it contains one scope only, which is required.
     */
    public function testScopeRequiredOneScopeProvided()
    {
        $this->auth(['scope' => 'test_scope'])
            ->request(true, 'test_scope')
            ->assertOk()
            ->assertSee('test_response');
    }

    /**
     * Verify the request passes when it contains multiple scopes including the one that is required.
     */
    public function testScopeRequiredMultipleScopesProvided()
    {
        $this->auth(['scope' => 'somescope test_scope somethingelse'])
            ->request(true, 'test_scope')
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

        $this->partialMock(TokenVerifierInterface::class, function (MockInterface $mock) use ($jwt) {
            $mock->shouldReceive('verify')->withArgs(['token'])->andReturn($jwt);
        });

        $this->request(true, '', function (Request $request) {
                // Apply the user resolver on the request and return its response.
                return [
                    'sub' => $request->user()->sub
                ];
            })
            ->assertOk()
            ->assertSimilarJson($jwt);
    }

    public function testCustomUserResolver()
    {
        $jwt = [
            'sub' => 'someuser',
        ];

        $this->partialMock(TokenVerifierInterface::class, function (MockInterface $mock) use ($jwt) {
            $mock->shouldReceive('verify')->withArgs(['token'])->andReturn($jwt);
        });

        $provider = $this->partialMock(UserProvider::class, function (MockInterface $mock) use ($jwt) {
            $mock->shouldReceive('retrieveById')->withArgs([$jwt['sub']])->andReturn(new User([
                'sub' => 'someuser',
                'name' => 'Kees',
            ]));
        });

        resolve(AuthManager::class)->provider('pc-users', fn () => $provider);

        $response = $this->request(true, '', function (Request $request) {
                // Apply the user resolver on the request and return its response.
                return [
                    'sub' => $request->user()->sub,
                    'name' => $request->user()->name,
                ];
            });

        $response
            ->assertOk()
            ->assertSimilarJson([
                'sub' => 'someuser',
                'name' => 'Kees',
            ]);
    }

    public function testNoIssuer()
    {
        Config::set('pricecypher-oidc.issuer', null);

        $this->request(true, 'test_scope')
            ->assertServerError()
            ->assertSee('Issuer must be provided');
    }
}
