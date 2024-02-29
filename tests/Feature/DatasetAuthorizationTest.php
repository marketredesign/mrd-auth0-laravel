<?php


namespace Marketredesign\MrdAuth0Laravel\Tests\Feature;

use Closure;
use Illuminate\Foundation\Application;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Marketredesign\MrdAuth0Laravel\Facades\PricecypherAuth;
use Marketredesign\MrdAuth0Laravel\Tests\TestCase;

class DatasetAuthorizationTest extends TestCase
{
    protected const BASE_USERS = 'https://datasets.test';

    /**
     * URI of the test route that is created for these tests.
     */
    private const ROUTE_URI = 'test_route';

    /**
     * Alias of the AuthorizeDatasetAccess middleware.
     */
    private const MIDDLEWARE_ALIAS = 'dataset.access';

    private const SUPPORTED_DATASET_KEYS = [
        'dataset_id',
        'datasetId',
        'datasetID',
    ];

    /**
     * @var bool Whether or not JWT authorization is enabled for the test route.
     */
    private $enableJWT = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withMiddleware(['jwt']);

        // TODO test both M2M, as well as bearer token from request, when sending request to other PC services.
        Http::preventStrayRequests();
        PricecypherAuth::fake();
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
        $app['config']->set('pricecypher.services.user_tool', self::BASE_USERS);
    }

    /**
     * Perform a GET request to the test endpoint, optionally including a route parameter
     *
     * @param string $requestMethod HTTP method to use for the route.
     * @param string|null $routeParamKey Name of the route parameter to include in the test endpoint.
     * @param string|null $routeParamVal Value of the route parameter to call the test endpoint with.
     * @param array $requestData Data to send in the request, e.g. query params or request body.
     * @param Closure|null $respHandler Response handler for endpoint. When null, a JSON containing `test_response`
     * will be returned.
     * @return TestResponse
     */
    private function request(
        string $requestMethod = 'GET',
        ?string $routeParamKey = null,
        ?string $routeParamVal = null,
        array $requestData = [],
        ?Closure $respHandler = null
    ): TestResponse {
        $route = self::ROUTE_URI;
        $middleware = [self::MIDDLEWARE_ALIAS];

        if (!empty($routeParamKey)) {
            $route .= '/{' . $routeParamKey . '}';
        }

        if ($this->enableJWT) {
            $middleware[] = 'jwt';
        }

        // Define a very simple testing endpoint, protected by the dataset authorization middleware.
        Route::middleware($middleware)
            ->match($requestMethod, $route, $respHandler ?? function () {
                return response()->json('test_response');
            })->name('test_route');

        return $this->json($requestMethod, route(self::ROUTE_URI, $routeParamVal), $requestData, [
            'Authorization' => 'Bearer token',
        ]);
    }

    /**
     * Verifies that a request without dataset does not perform any dataset authorization.
     */
    public function testNoDatasetId()
    {
        // No dataset is specified in the request, so no authorization should be performed.
        $this->request()->assertOk()->assertSee('test_response');

        // Verify that no API requests to user tool were made.
        Http::assertSentCount(0);
    }

    /**
     * Verifies that access to endpoints protected by the middleware is not authorized when the route uses a supported
     * dataset id key as route parameter and the user has access to no datasets at all.
     */
    public function testRouteDatasetNoAuthorized()
    {
        // Return empty response (from mocked user tool dataset endpoint) once per supported dataset key.
        Http::fake([self::BASE_USERS . '/api/datasets?*' => Http::response(['datasets' => []])]);

        // Create and send request to test endpoint for each supported dataset key as route parameter.
        foreach (self::SUPPORTED_DATASET_KEYS as $routeParamKey) {
            $this->auth()
                ->request('GET', $routeParamKey, 1)
                ->assertSee('Unauthorized dataset');
        }
    }

    /**
     * Verifies that access to endpoints protected by the middleware is not authorized for GET requests that use
     * a supported dataset id key as query parameter and the user has access to no datasets at all.
     */
    public function testQueryParamDatasetNoAuthorized()
    {
        // Return empty response (from mocked user tool dataset endpoint) once per supported dataset key.
        Http::fake([self::BASE_USERS . '/api/datasets?*' => Http::response(['datasets' => []])]);

        // Create and send request to test endpoint for each supported dataset key as route parameter.
        foreach (self::SUPPORTED_DATASET_KEYS as $queryParamKey) {
            $this->auth()
                ->request('GET', null, null, [
                    $queryParamKey => 1,
                ])
                ->assertForbidden()
                ->assertSee('Unauthorized dataset');
        }
    }

    /**
     * Verifies that access to endpoints protected by the middleware is not authorized for POST requests that use
     * a supported dataset id key in the body and the user has access to no datasets at all.
     */
    public function testPostDatasetNoAuthorized()
    {
        // Return empty response (from mocked user tool dataset endpoint) once per supported dataset key.
        Http::fake([self::BASE_USERS . '/api/datasets?*' => Http::response(['datasets' => []])]);

        // Create and send request to test endpoint for each supported dataset key as route parameter.
        foreach (self::SUPPORTED_DATASET_KEYS as $queryParamKey) {
            $this->auth()
                ->request('POST', null, null, [
                    $queryParamKey => 1,
                ])
                ->assertForbidden()
                ->assertSee('Unauthorized dataset');
        }
    }

    /**
     * Verifies that access to endpoints protected by the middleware is not authorized when the route uses a supported
     * dataset id key as route parameter and the user requests access to an unauthorized dataset.
     */
    public function testRouteDatasetUnauthorized()
    {
        // Return mocked response as given by user tool once per supported dataset key.
        Http::fake([self::BASE_USERS . '/api/datasets?*' => Http::response(['datasets' => [
            ['id' => 7, 'name' => 'Cool dataset', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['id' => 6, 'name' => 'Sjaak & Co', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['id' => 1, 'name' => 'Spotify', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
        ]])]);

        // Create and send request to test endpoint for each supported dataset key as route parameter.
        foreach (self::SUPPORTED_DATASET_KEYS as $routeParamKey) {
            $this->auth()
                ->request('GET', $routeParamKey, 2)
                ->assertForbidden()
                ->assertSee('Unauthorized dataset');
        }
    }

    /**
     * Verifies that access to endpoints protected by the middleware is not authorized for GET requests that use
     * a supported dataset id key as query parameter and the user requests access to an unauthorized dataset.
     */
    public function testQueryParamDatasetUnauthorized()
    {
        // Return mocked response as given by user tool once per supported dataset key.
        Http::fake([self::BASE_USERS . '/api/datasets?*' => Http::response(['datasets' => [
            ['id' => 7, 'name' => 'Cool dataset', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['id' => 6, 'name' => 'Sjaak & Co', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['id' => 1, 'name' => 'Spotify', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
        ]])]);

        // Create and send request to test endpoint for each supported dataset key as route parameter.
        foreach (self::SUPPORTED_DATASET_KEYS as $queryParamKey) {
            $this->auth()
                ->request('GET', null, null, [
                    $queryParamKey => 2,
                ])
                ->assertForbidden()
                ->assertSee('Unauthorized dataset');
        }
    }

    /**
     * Verifies that access to endpoints protected by the middleware is not authorized for POST requests that use
     * a supported dataset id key in the body and the user requests access to an unauthorized dataset.
     */
    public function testPostDatasetUnauthorized()
    {
        // Return mocked response as given by user tool once per supported dataset key.
        Http::fake([self::BASE_USERS . '/api/datasets?*' => Http::response(['datasets' => [
            ['id' => 7, 'name' => 'Cool dataset', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['id' => 6, 'name' => 'Sjaak & Co', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['id' => 1, 'name' => 'Spotify', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
        ]])]);


        // Create and send request to test endpoint for each supported dataset key as route parameter.
        foreach (self::SUPPORTED_DATASET_KEYS as $queryParamKey) {
            $this->auth()
                ->request('POST', null, null, [
                    $queryParamKey => 2,
                ])
                ->assertForbidden()
                ->assertSee('Unauthorized dataset');
        }
    }

    /**
     * Verifies that access to endpoints protected by the middleware is authorized when the route uses a supported
     * dataset id key as route parameter and the user requests access to an authorized dataset.
     */
    public function testRouteDatasetAuthorized()
    {
        // Return mocked response as given by user tool once per supported dataset key.
        Http::fake([self::BASE_USERS . '/api/datasets?*' => Http::response(['datasets' => [
            ['id' => 7, 'name' => 'Cool dataset', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['id' => 6, 'name' => 'Sjaak & Co', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['id' => 1, 'name' => 'Spotify', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
        ]])]);

        // Create and send request to test endpoint for each supported dataset key as route parameter.
        foreach (self::SUPPORTED_DATASET_KEYS as $routeParamKey) {
            $this->auth()
                ->request('GET', $routeParamKey, 6)
                ->assertOk()
                ->assertSee('test_response');
        }
    }

    /**
     * Verifies that access to endpoints protected by the middleware is authorized for GET requests that use
     * a supported dataset id key as query parameter and the user requests access to an authorized dataset.
     */
    public function testQueryParamDatasetAuthorized()
    {
        // Return mocked response as given by user tool once per supported dataset key.
        Http::fake([self::BASE_USERS . '/api/datasets?*' => Http::response(['datasets' => [
            ['id' => 7, 'name' => 'Cool dataset', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['id' => 6, 'name' => 'Sjaak & Co', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['id' => 1, 'name' => 'Spotify', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
        ]])]);

        // Create and send request to test endpoint for each supported dataset key as route parameter.
        foreach (self::SUPPORTED_DATASET_KEYS as $queryParamKey) {
            $this->auth()
                ->request('GET', null, null, [
                    $queryParamKey => 6,
                ])
                ->assertOk()
                ->assertSee('test_response');
        }
    }

    /**
     * Verifies that access to endpoints protected by the middleware is authorized for POST requests that use
     * a supported dataset id key in the body and the user requests access to an authorized dataset.
     */
    public function testPostDatasetAuthorized()
    {
        // Return mocked response as given by user tool once per supported dataset key.
        Http::fake([self::BASE_USERS . '/api/datasets?*' => Http::response(['datasets' => [
            ['id' => 7, 'name' => 'Cool dataset', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['id' => 6, 'name' => 'Sjaak & Co', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['id' => 1, 'name' => 'Spotify', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
        ]])]);

        // Create and send request to test endpoint for each supported dataset key as route parameter.
        foreach (self::SUPPORTED_DATASET_KEYS as $queryParamKey) {
            $this->auth()
                ->request('POST', null, null, [
                    $queryParamKey => 6,
                ])
                ->assertOk()
                ->assertSee('test_response');
        }
    }

    /**
     * Verifies that access to endpoints protected by the middleware is unauthorized for GET and POST requests that
     * specify different dataset IDs as route and query parameters.
     */
    public function testDifferentDatasetIdsInRouteAndRequest()
    {
        // Send GET and POST requests to test endpoint for each supported dataset key as route and query parameter.
        foreach (['GET', 'POST'] as $requestMethod) {
            foreach (self::SUPPORTED_DATASET_KEYS as $routeParamKey) {
                foreach (self::SUPPORTED_DATASET_KEYS as $queryParamKey) {
                    // Specify two different dataset IDs.
                    $this->auth()
                        ->request($requestMethod, $routeParamKey, 7, [
                            $queryParamKey => 6,
                        ])
                        ->assertUnauthorized()
                        ->assertSee('Multiple dataset IDs');
                }
            }
        }
    }

    /**
     * Verifies that access to endpoints protected by the middleware is unauthorized for GET and POST requests that
     * specify different dataset IDs in the request data.
     */
    public function testDifferentDatasetIdsInRequest()
    {
        // Send GET and POST requests.
        foreach (['GET', 'POST'] as $requestMethod) {
            $requestData = [];

            // Add all supported dataset ID keys to the request data, with distinct values.
            foreach (self::SUPPORTED_DATASET_KEYS as $i => $reqDataKey) {
                $requestData[$reqDataKey] = $i;
            }

            // Send request with no dataset ID in the route, but multiple dataset IDs in the request data.
            $this->auth()
                ->request($requestMethod, null, null, $requestData)
                ->assertUnauthorized()
                ->assertSee('Multiple dataset IDs');
        }
    }

    /**
     * Verifies that dataset access is unauthorized when a request exception occurs when calling the user tool API.
     */
    public function testUserToolRequestException()
    {
        // Returned mocked request exception once per supported dataset key.
        Http::fake([self::BASE_USERS . '/api/datasets?*' => Http::response('Some fake error', 400)]);

        // Create and send request to test endpoint for each supported dataset key as route parameter.
        foreach (self::SUPPORTED_DATASET_KEYS as $routeParamKey) {
            $this->auth()
                ->request('GET', $routeParamKey, 2)
                ->assertUnauthorized()
                ->assertSee('Unable to authorize dataset access.');
        }
    }

    /**
     * Verifies that no response is given when a transfer exception occurs when calling the user tool API.
     * NB: transfer exception is the top level exception of Guzzle (and thus also includes the request exceptions) but
     * we cannot recover from it in most cases, so we allow a 500 Internal server to be returned in this case.
     */
    public function testUserToolTransferException()
    {
        // Returned mocked transfer exception once per supported dataset key.
        Http::fake([self::BASE_USERS . '/api/datasets?*' => Http::response('Some fake error', 500)]);

        // Create and send request to test endpoint for each supported dataset key as route parameter.
        foreach (self::SUPPORTED_DATASET_KEYS as $routeParamKey) {
            $this->auth()
                ->request('GET', $routeParamKey, 2)
                ->assertDontSee('test_response');
        }
    }

    /**
     * Verifies that datasets are authorized correctly when two users make requests, and no JWT middleware is used.
     */
    public function testMultipleUsersNoJWT()
    {
        // Disable JWT authorization.
        $this->enableJWT = false;

        // Create 2 different fake responses, for 2 different users.
        $response1 = Http::response(['datasets' => [
            ['id' => 7, 'name' => 'Cool dataset', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['id' => 6, 'name' => 'Sjaak & Co', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['id' => 1, 'name' => 'Spotify', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
        ]]);
        $response2 = Http::response(['datasets' => [
            ['id' => 6, 'name' => 'Sjaak & Co', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['id' => 1, 'name' => 'Spotify', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
        ]]);

        $seq = Http::sequence();

        collect(self::SUPPORTED_DATASET_KEYS)->each(fn() => $seq
            ->pushResponse($response1)
            ->pushResponse($response2)
            ->pushResponse($response1)
            ->pushResponse($response2));

        Http::fake([self::BASE_USERS . '/api/datasets?*' => $seq]);

        // Create and send request to test endpoint for each supported dataset key as route parameter.
        foreach (self::SUPPORTED_DATASET_KEYS as $routeParamKey) {
            // Make sure multiple requests are sent per user.
            $this->request('GET', $routeParamKey, 7)
                ->assertOk()
                ->assertSee('test_response');

            $this->request('GET', $routeParamKey, 7)
                ->assertForbidden()
                ->assertSee('Unauthorized dataset');

            $this->request('GET', $routeParamKey, 7)
                ->assertOk()
                ->assertSee('test_response');

            $this->request('GET', $routeParamKey, 7)
                ->assertForbidden()
                ->assertSee('Unauthorized dataset');
        }

        // Verify that an API call was made per request.
        self::assertCount(4 * count(self::SUPPORTED_DATASET_KEYS), Http::recorded(
            fn(\Illuminate\Http\Client\Request $request) => Str::startsWith($request->url(), self::BASE_USERS)),
        );
    }

    /**
     * Verifies that datasets are authorized correctly when two users make requests, and JWT middleware is used.
     */
    public function testMultipleUsersWithJWT()
    {
        // Enable JWT authorization such that a user ID is present in the request and thus caching should be enabled.
        $this->enableJWT = true;

        // Create 2 different fake responses, for 2 different users.
        $response1 = Http::response(['datasets' => [
            ['id' => 7, 'name' => 'Cool dataset', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['id' => 6, 'name' => 'Sjaak & Co', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['id' => 1, 'name' => 'Spotify', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
        ]]);
        $response2 = Http::response(['datasets' => [
            ['id' => 6, 'name' => 'Sjaak & Co', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['id' => 1, 'name' => 'Spotify', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
        ]]);

        Http::fake([self::BASE_USERS . '/api/datasets?*' => Http::sequence([$response1, $response2])]);

        // Create and send request to test endpoint for each supported dataset key as route parameter.
        foreach (self::SUPPORTED_DATASET_KEYS as $routeParamKey) {
            // Make sure multiple requests are sent per user.
            $this->auth(['sub' => 'user1'])
                ->request('GET', $routeParamKey, 7)
                ->assertOk()
                ->assertSee('test_response');

            $this->auth(['sub' => 'user2'])
                ->request('GET', $routeParamKey, 7)
                ->assertForbidden()
                ->assertSee('Unauthorized dataset');

            $this->auth(['sub' => 'user1'])
                ->request('GET', $routeParamKey, 7)
                ->assertOk()
                ->assertSee('test_response');

            $this->auth(['sub' => 'user2'])
                ->request('GET', $routeParamKey, 7)
                ->assertForbidden()
                ->assertSee('Unauthorized dataset');
        }

        // Verify that only 2 API calls were made.
        self::assertCount(2, Http::recorded(
            fn(\Illuminate\Http\Client\Request $request) => Str::startsWith($request->url(), self::BASE_USERS)),
        );
//        self::assertCount(2, $this->guzzleContainer);
    }

    /**
     * Verifies that the cache TTL can be set using a config value.
     */
    public function testGetCachingTTL()
    {
        // Enable JWT authorization such that a user ID is present in the request and thus caching should be enabled.
        $this->enableJWT = true;

        // Return empty response twice.
        Http::fake([self::BASE_USERS . '/api/datasets?*' => Http::response(['datasets' => []])]);

        // Set cache TTL to 10 seconds in the config.
        Config::set('pricecypher.cache_ttl', 10);

        // Send request to test endpoint for each supported dataset key as route parameter.
        foreach (self::SUPPORTED_DATASET_KEYS as $routeParamKey) {
            // Send same request twice.
            $this->auth(['sub' => 'user1'])
                ->request('GET', $routeParamKey, 7)
                ->assertForbidden()
                ->assertSee('Unauthorized dataset');

            $this->auth(['sub' => 'user1'])
                ->request('GET', $routeParamKey, 7)
                ->assertForbidden()
                ->assertSee('Unauthorized dataset');
        }

        // Verify only 1 api call was made.
        self::assertCount(1, Http::recorded(
            fn(\Illuminate\Http\Client\Request $request) => Str::startsWith($request->url(), self::BASE_USERS)),
        );

        // Increment time such that cache TTL should have passed.
        Carbon::setTestNow(Carbon::now()->addSeconds(11));

        // Send same request again, and expect a new API call to be made.
        foreach (self::SUPPORTED_DATASET_KEYS as $routeParamKey) {
            // Send same request twice.
            $this->auth(['sub' => 'user1'])
                ->request('GET', $routeParamKey, 7)
                ->assertForbidden()
                ->assertSee('Unauthorized dataset');
        }

        // Verify now a total of two api calls was made.
        self::assertCount(2, Http::recorded(
            fn(\Illuminate\Http\Client\Request $request) => Str::startsWith($request->url(), self::BASE_USERS)),
        );
    }
}
