<?php


namespace Marketredesign\MrdAuth0Laravel\Tests\Feature;

use Closure;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Request;
use Illuminate\Foundation\Application;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Marketredesign\MrdAuth0Laravel\Tests\TestCase;

class DatasetAuthorizationTest extends TestCase
{
    /**
     * Overwrite default of 5 since some of our tests send more requests.
     */
    protected const RESPONSE_QUEUE_SIZE = 15;

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

    /**
     * Define environment setup.
     *
     * @param  Application  $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        // Set the Laravel Auth0 config values which are used to some values.
        $app['config']->set('mrd-auth0.guzzle_options', $this->createTestingGuzzleOptions());
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
        self::assertCount(0, $this->guzzleContainer);
    }

    /**
     * Verifies that access to endpoints protected by the middleware is not authorized when the route uses a supported
     * dataset id key as route parameter and the user has access to no datasets at all.
     */
    public function testRouteDatasetNoAuthorized()
    {
        // Return empty response (from mocked user tool dataset endpoint) once per supported dataset key.
        $response = new Response(200, [], '{"datasets": []}');
        $this->mockedResponses = array_fill(0, count(self::SUPPORTED_DATASET_KEYS), $response);

        // Create and send request to test endpoint for each supported dataset key as route parameter.
        foreach (self::SUPPORTED_DATASET_KEYS as $routeParamKey) {
            $this->request('GET', $routeParamKey, 1)
                ->assertForbidden()
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
        $response = new Response(200, [], '{"datasets": []}');
        $this->mockedResponses = array_fill(0, count(self::SUPPORTED_DATASET_KEYS), $response);

        // Create and send request to test endpoint for each supported dataset key as route parameter.
        foreach (self::SUPPORTED_DATASET_KEYS as $routeParamKey) {
            $this->request('GET', null, null, [
                    $routeParamKey => 1,
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
        $response = new Response(200, [], '{"datasets": []}');
        $this->mockedResponses = array_fill(0, count(self::SUPPORTED_DATASET_KEYS), $response);

        // Create and send request to test endpoint for each supported dataset key as route parameter.
        foreach (self::SUPPORTED_DATASET_KEYS as $routeParamKey) {
            $this->request('POST', null, null, [
                $routeParamKey => 1,
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
        $response = new Response(200, [], '{"datasets":[{"id":7,"name":"Cool dataset",
        "created_at":"2021-03-15T15:02:59.000000Z","updated_at":"2021-03-15T15:02:59.000000Z"},{"id":6,"name":
        "Sjaak & Co","created_at":"2021-03-04T00:42:10.000000Z","updated_at":"2021-03-04T00:42:10.000000Z"},{"id":1,
        "name":"Spotify","created_at":"2020-11-10T17:09:16.000000Z","updated_at":"2020-11-10T17:09:16.000000Z"}]}');
        $this->mockedResponses = array_fill(0, count(self::SUPPORTED_DATASET_KEYS), $response);

        // Create and send request to test endpoint for each supported dataset key as route parameter.
        foreach (self::SUPPORTED_DATASET_KEYS as $routeParamKey) {
            $this->request('GET', $routeParamKey, 2)
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
        $response = new Response(200, [], '{"datasets":[{"id":7,"name":"Cool dataset",
        "created_at":"2021-03-15T15:02:59.000000Z","updated_at":"2021-03-15T15:02:59.000000Z"},{"id":6,"name":
        "Sjaak & Co","created_at":"2021-03-04T00:42:10.000000Z","updated_at":"2021-03-04T00:42:10.000000Z"},{"id":1,
        "name":"Spotify","created_at":"2020-11-10T17:09:16.000000Z","updated_at":"2020-11-10T17:09:16.000000Z"}]}');
        $this->mockedResponses = array_fill(0, count(self::SUPPORTED_DATASET_KEYS), $response);

        // Create and send request to test endpoint for each supported dataset key as route parameter.
        foreach (self::SUPPORTED_DATASET_KEYS as $routeParamKey) {
            $this->request('GET', null, null, [
                $routeParamKey => 2,
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
        $response = new Response(200, [], '{"datasets":[{"id":7,"name":"Cool dataset",
        "created_at":"2021-03-15T15:02:59.000000Z","updated_at":"2021-03-15T15:02:59.000000Z"},{"id":6,"name":
        "Sjaak & Co","created_at":"2021-03-04T00:42:10.000000Z","updated_at":"2021-03-04T00:42:10.000000Z"},{"id":1,
        "name":"Spotify","created_at":"2020-11-10T17:09:16.000000Z","updated_at":"2020-11-10T17:09:16.000000Z"}]}');
        $this->mockedResponses = array_fill(0, count(self::SUPPORTED_DATASET_KEYS), $response);

        // Create and send request to test endpoint for each supported dataset key as route parameter.
        foreach (self::SUPPORTED_DATASET_KEYS as $routeParamKey) {
            $this->request('POST', null, null, [
                $routeParamKey => 2,
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
        $response = new Response(200, [], '{"datasets":[{"id":7,"name":"Cool dataset",
        "created_at":"2021-03-15T15:02:59.000000Z","updated_at":"2021-03-15T15:02:59.000000Z"},{"id":6,"name":
        "Sjaak & Co","created_at":"2021-03-04T00:42:10.000000Z","updated_at":"2021-03-04T00:42:10.000000Z"},{"id":1,
        "name":"Spotify","created_at":"2020-11-10T17:09:16.000000Z","updated_at":"2020-11-10T17:09:16.000000Z"}]}');
        $this->mockedResponses = array_fill(0, count(self::SUPPORTED_DATASET_KEYS), $response);

        // Create and send request to test endpoint for each supported dataset key as route parameter.
        foreach (self::SUPPORTED_DATASET_KEYS as $routeParamKey) {
            $this->request('GET', $routeParamKey, 6)
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
        $response = new Response(200, [], '{"datasets":[{"id":7,"name":"Cool dataset",
        "created_at":"2021-03-15T15:02:59.000000Z","updated_at":"2021-03-15T15:02:59.000000Z"},{"id":6,"name":
        "Sjaak & Co","created_at":"2021-03-04T00:42:10.000000Z","updated_at":"2021-03-04T00:42:10.000000Z"},{"id":1,
        "name":"Spotify","created_at":"2020-11-10T17:09:16.000000Z","updated_at":"2020-11-10T17:09:16.000000Z"}]}');
        $this->mockedResponses = array_fill(0, count(self::SUPPORTED_DATASET_KEYS), $response);

        // Create and send request to test endpoint for each supported dataset key as route parameter.
        foreach (self::SUPPORTED_DATASET_KEYS as $routeParamKey) {
            $this->request('GET', null, null, [
                $routeParamKey => 6,
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
        $response = new Response(200, [], '{"datasets":[{"id":7,"name":"Cool dataset",
        "created_at":"2021-03-15T15:02:59.000000Z","updated_at":"2021-03-15T15:02:59.000000Z"},{"id":6,"name":
        "Sjaak & Co","created_at":"2021-03-04T00:42:10.000000Z","updated_at":"2021-03-04T00:42:10.000000Z"},{"id":1,
        "name":"Spotify","created_at":"2020-11-10T17:09:16.000000Z","updated_at":"2020-11-10T17:09:16.000000Z"}]}');
        $this->mockedResponses = array_fill(0, count(self::SUPPORTED_DATASET_KEYS), $response);

        // Create and send request to test endpoint for each supported dataset key as route parameter.
        foreach (self::SUPPORTED_DATASET_KEYS as $routeParamKey) {
            $this->request('POST', null, null, [
                $routeParamKey => 6,
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
                    $this->request($requestMethod, $routeParamKey, 7, [
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
            $this->request($requestMethod, null, null, $requestData)
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
        $response = new RequestException('Some fake error', new Request('GET', 'test'));
        $this->mockedResponses = array_fill(0, count(self::SUPPORTED_DATASET_KEYS), $response);

        // Create and send request to test endpoint for each supported dataset key as route parameter.
        foreach (self::SUPPORTED_DATASET_KEYS as $routeParamKey) {
            $this->request('GET', $routeParamKey, 2)
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
        $response = new TransferException('Some fake transfer exception');
        $this->mockedResponses = array_fill(0, count(self::SUPPORTED_DATASET_KEYS), $response);

        // Create and send request to test endpoint for each supported dataset key as route parameter.
        foreach (self::SUPPORTED_DATASET_KEYS as $routeParamKey) {
            $this->request('GET', $routeParamKey, 2)
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
        $response1 = new Response(200, [], '{"datasets":[{"id":7,"name":"Cool dataset",
        "created_at":"2021-03-15T15:02:59.000000Z","updated_at":"2021-03-15T15:02:59.000000Z"},{"id":6,"name":
        "Sjaak & Co","created_at":"2021-03-04T00:42:10.000000Z","updated_at":"2021-03-04T00:42:10.000000Z"},{"id":1,
        "name":"Spotify","created_at":"2020-11-10T17:09:16.000000Z","updated_at":"2020-11-10T17:09:16.000000Z"}]}');
        $response2 = new Response(200, [], '{"datasets":[{"id":6,"name":
        "Sjaak & Co","created_at":"2021-03-04T00:42:10.000000Z","updated_at":"2021-03-04T00:42:10.000000Z"},{"id":1,
        "name":"Spotify","created_at":"2020-11-10T17:09:16.000000Z","updated_at":"2020-11-10T17:09:16.000000Z"}]}');

        // Create and send request to test endpoint for each supported dataset key as route parameter.
        foreach (self::SUPPORTED_DATASET_KEYS as $routeParamKey) {
            // Make sure multiple requests are sent per user.
            $this->mockedResponses[] = $response1;

            $this->request('GET', $routeParamKey, 7)
                ->assertOk()
                ->assertSee('test_response');

            $this->mockedResponses[] = $response2;

            $this->request('GET', $routeParamKey, 7)
                ->assertForbidden()
                ->assertSee('Unauthorized dataset');

            $this->mockedResponses[] = $response1;

            $this->request('GET', $routeParamKey, 7)
                ->assertOk()
                ->assertSee('test_response');

            $this->mockedResponses[] = $response2;

            $this->request('GET', $routeParamKey, 7)
                ->assertForbidden()
                ->assertSee('Unauthorized dataset');
        }

        // Verify that an API call was made per request.
        self::assertCount(4 * count(self::SUPPORTED_DATASET_KEYS), $this->guzzleContainer);
    }

    /**
     * Verifies that datasets are authorized correctly when two users make requests, and JWT middleware is used.
     */
    public function testMultipleUsersWithJWT()
    {
        // Enable JWT authorization such that a user ID is present in the request and thus caching should be enabled.
        $this->enableJWT = true;

        // Create 2 different fake responses, for 2 different users.
        $response1 = new Response(200, [], '{"datasets":[{"id":7,"name":"Cool dataset",
        "created_at":"2021-03-15T15:02:59.000000Z","updated_at":"2021-03-15T15:02:59.000000Z"},{"id":6,"name":
        "Sjaak & Co","created_at":"2021-03-04T00:42:10.000000Z","updated_at":"2021-03-04T00:42:10.000000Z"},{"id":1,
        "name":"Spotify","created_at":"2020-11-10T17:09:16.000000Z","updated_at":"2020-11-10T17:09:16.000000Z"}]}');
        $response2 = new Response(200, [], '{"datasets":[{"id":6,"name":
        "Sjaak & Co","created_at":"2021-03-04T00:42:10.000000Z","updated_at":"2021-03-04T00:42:10.000000Z"},{"id":1,
        "name":"Spotify","created_at":"2020-11-10T17:09:16.000000Z","updated_at":"2020-11-10T17:09:16.000000Z"}]}');

        $this->mockedResponses = [$response1, $response2];

        // Create and send request to test endpoint for each supported dataset key as route parameter.
        foreach (self::SUPPORTED_DATASET_KEYS as $routeParamKey) {
            // Make sure multiple requests are sent per user.

            $this->userId = 'user1';
            $this->mockAuth0Service([]);

            $this->request('GET', $routeParamKey, 7)
                ->assertOk()
                ->assertSee('test_response');

            $this->userId = 'user2';
            $this->mockAuth0Service([]);

            $this->request('GET', $routeParamKey, 7)
                ->assertForbidden()
                ->assertSee('Unauthorized dataset');

            $this->userId = 'user1';
            $this->mockAuth0Service([]);

            $this->request('GET', $routeParamKey, 7)
                ->assertOk()
                ->assertSee('test_response');

            $this->userId = 'user2';
            $this->mockAuth0Service([]);

            $this->request('GET', $routeParamKey, 7)
                ->assertForbidden()
                ->assertSee('Unauthorized dataset');
        }

        // Verify that only 2 API calls were made.
        self::assertCount(2, $this->guzzleContainer);
    }

    /**
     * Verifies that the cache TTL can be set using a config value.
     */
    public function testGetCachingTTL()
    {
        // Enable JWT authorization such that a user ID is present in the request and thus caching should be enabled.
        $this->enableJWT = true;

        // Return empty response twice.
        $response = new Response(200, [], '{"datasets": []}');
        $this->mockedResponses = [$response, $response];

        // Set cache TTL to 10 seconds in the config.
        Config::set('mrd-auth0.cache_ttl', 10);

        // Send request to test endpoint for each supported dataset key as route parameter.
        foreach (self::SUPPORTED_DATASET_KEYS as $routeParamKey) {
            $this->userId = 'user1';
            $this->mockAuth0Service([]);

            // Send same request twice.
            $this->request('GET', $routeParamKey, 7)
                ->assertForbidden()
                ->assertSee('Unauthorized dataset');
            $this->request('GET', $routeParamKey, 7)
                ->assertForbidden()
                ->assertSee('Unauthorized dataset');
        }

        // Verify only 1 api call was made.
        self::assertCount(1, $this->guzzleContainer);

        // Increment time such that cache TTL should have passed.
        Carbon::setTestNow(Carbon::now()->addSeconds(11));

        // Send same request again, and expect a new API call to be made.
        foreach (self::SUPPORTED_DATASET_KEYS as $routeParamKey) {
            $this->userId = 'user1';
            $this->mockAuth0Service([]);

            // Send same request twice.
            $this->request('GET', $routeParamKey, 7)
                ->assertForbidden()
                ->assertSee('Unauthorized dataset');
        }

        // Verify now a total of two api calls was made.
        self::assertCount(2, $this->guzzleContainer);
    }
}
