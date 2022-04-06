<?php


namespace Marketredesign\MrdAuth0Laravel\Tests\Feature;

use Carbon\Carbon;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Marketredesign\MrdAuth0Laravel\Contracts\DatasetRepository;
use Marketredesign\MrdAuth0Laravel\Tests\TestCase;

class DatasetRepositoryTest extends TestCase
{
    /**
     * @var DatasetRepository
     */
    protected $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = App::make(DatasetRepository::class);
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
        $app['config']->set('mrd-auth0.guzzle_options', $this->createTestingGuzzleOptions());
    }

    /**
     * Asserts that the given dataset is as expected.
     *
     * @param array|null $dataset
     * @param string $name
     * @param string $createdAt
     * @param string $updatedAt
     */
    protected function assertDataset(?array $dataset, string $name, string $createdAt, string $updatedAt)
    {
        self::assertNotNull($dataset);
        
        self::assertEquals($name, $dataset['name']);

        $actCreatedAt = Carbon::parse($dataset['created_at']);
        $actUpdatedAt = Carbon::parse($dataset['updated_at']);

        self::assertTrue(Carbon::parse($createdAt)->equalTo($actCreatedAt));
        self::assertTrue(Carbon::parse($updatedAt)->equalTo($actUpdatedAt));
    }

    /**
     * Verifies that our implementation of the Dataset Repository is bound in the service container, and that it can be
     * instantiated.
     */
    public function testServiceBinding()
    {
        // Verify it is indeed our instance.
        $this->assertInstanceOf(\Marketredesign\MrdAuth0Laravel\Repository\DatasetRepository::class, $this->repo);
    }

    /**
     * Verifies that the base URL of the user tool can be configured.
     */
    public function testUserToolBaseConfigurable()
    {
        // Return empty response 3 times.
        $response = new Response(200, [], '{"datasets": []}');
        $this->mockedResponses = [$response, $response, $response];

        foreach (['https://users.com', 'https://users.pricecypher.com', 'http://tests.pc.test/api'] as $i => $baseUrl) {
            Config::set('mrd-auth0.user_tool_url', $baseUrl);

            // Call function under test.
            $this->repo->getUserDatasetIds();

            // Expect number of api calls to increment each time by one.
            self::assertCount(1 + $i, $this->guzzleContainer);

            // Find the request that was sent to the user tool.
            $request = $this->guzzleContainer[$i]['request'];

            // Verify correct uri was called.
            self::assertStringStartsWith($baseUrl, (string) $request->getUri());
        }
    }

    /**
     * Verifies that an empty collection of dataset IDs is returned when the user has access to no datasets.
     */
    public function testGetIdsNoDatasets()
    {
        // Return empty response
        $this->mockedResponses = [new Response(200, [], '{"datasets": []}')];

        // Call function under test.
        $datasetIds = $this->repo->getUserDatasetIds();

        // Verify response indeed empty.
        self::assertNotNull($datasetIds);
        self::assertTrue($datasetIds->isEmpty());

        // Expect 1 api call.
        self::assertCount(1, $this->guzzleContainer);

        // Find the request that was sent to the user tool.
        $request = $this->guzzleContainer[0]['request'];

        // Verify correct endpoint was called.
        self::assertEquals('/api/datasets', $request->getUri()->getPath());
    }

    /**
     * Verifies that an empty collection of datasets is returned when the user has access to no datasets.
     */
    public function testGetDatasetsNoDatasets()
    {
        // Return empty response
        $this->mockedResponses = [new Response(200, [], '{"datasets": []}')];

        // Call function under test.
        $datasets = $this->repo->getUserDatasets();

        // Verify response indeed empty.
        self::assertNotNull($datasets);
        self::assertTrue($datasets->isEmpty());
    }

    /**
     * Verifies that retrieving dataset IDs works as expected.
     */
    public function testGetIds()
    {
        // Return mocked response as given by user tool.
        $this->mockedResponses = [new Response(200, [], '{"datasets":[{"id":7,"name":"Cool dataset",
        "created_at":"2021-03-15T15:02:59.000000Z","updated_at":"2021-03-15T15:02:59.000000Z"},{"id":6,"name":
        "Sjaak & Co","created_at":"2021-03-04T00:42:10.000000Z","updated_at":"2021-03-04T00:42:10.000000Z"},{"id":1,
        "name":"Spotify","created_at":"2020-11-10T17:09:16.000000Z","updated_at":"2020-11-10T17:09:16.000000Z"}]}')];

        // Call function under test.
        $datasetIds = $this->repo->getUserDatasetIds();

        // Verify response contains exactly the 3 dataset IDs that we expect.
        self::assertNotNull($datasetIds);
        self::assertEquals(3, $datasetIds->count());
        self::assertContains(1, $datasetIds);
        self::assertContains(6, $datasetIds);
        self::assertContains(7, $datasetIds);
    }

    /**
     * Verifies that retrieving datasets works as expected.
     */
    public function testGetDatasets()
    {
        // Return mocked response as given by user tool.
        $this->mockedResponses = [new Response(200, [], '{"datasets":[{"id":7,"name":"Cool dataset",
        "created_at":"2021-03-15T15:02:59.000000Z","updated_at":"2021-03-15T15:02:59.000000Z"},{"id":6,"name":
        "Sjaak & Co","created_at":"2021-03-04T00:42:10.000000Z","updated_at":"2021-03-06T00:23:00.000060Z"},{"id":1,
        "name":"Spotify","created_at":"2020-11-10T17:09:16.000000Z","updated_at":"2020-12-10T18:09:22.000000Z"}]}')];

        // Call function under test.
        $resourceCollection = $this->repo->getUserDatasets();
        // Resolve the resource collection, such that we can verify its contents.
        $datasets = collect($resourceCollection->resolve());

        // Verify response contains exactly 3 datasets.
        self::assertNotNull($datasets);
        self::assertEquals(3, $datasets->count());

        // Find datasets by ID in the response.
        $dataset1 = $datasets->firstWhere('id', 1);
        $dataset6 = $datasets->firstWhere('id', 6);
        $dataset7 = $datasets->firstWhere('id', 7);

        // Verify that datasets are as expected.
        $this->assertDataset($dataset1, 'Spotify', '2020-11-10T17:09:16.000000Z', '2020-12-10T18:09:22.000000Z');
        $this->assertDataset($dataset6, 'Sjaak & Co', '2021-03-04T00:42:10.000000Z', '2021-03-06T00:23:00.000060Z');
        $this->assertDataset($dataset7, 'Cool dataset', '2021-03-15T15:02:59.000000Z', '2021-03-15T15:02:59.000000Z');
    }

    /**
     * Verifies that the bearer token is used in the request to the user tool.
     */
    public function testBearerTokenInRequest()
    {
        // Return empty response 3 times.
        $response = new Response(200, [], '{"datasets": []}');
        $this->mockedResponses = [$response, $response, $response];

        foreach (['token', 'eyaskjfasas', 'myVerycooltokensTrinGthatisActuallYNojwT'] as $i => $token) {
            // We set the header as part of the request that is currently "being handled the framework".
            request()->headers->set('Authorization', "Bearer $token");

            // Call function under test.
            $this->repo->getUserDatasets();

            // Find the request that is made to the user tool. NB: this is a different request than above.
            $request = $this->guzzleContainer[$i]['request'];

            self::assertArrayHasKey('Authorization', $request->getHeaders());
            self::assertCount(1, $request->getHeader('Authorization'));
            self::assertEquals("Bearer $token", $request->getHeader('Authorization')[0]);
        }
    }

    /**
     * Verifies that the `managed_only` query parameter is sent to the user tool when requesting datasets (or IDs).
     */
    public function testManagedOnlyParameter()
    {
        // Return empty response 4 times.
        $response = new Response(200, [], '{"datasets": []}');
        $this->mockedResponses = [$response, $response, $response, $response];

        foreach ([false, true] as $i => $bool) {
            // Call functions under test.
            $this->repo->getUserDatasetIds($bool);
            $this->repo->getUserDatasets($bool);

            $boolBit = $bool ? 1 : 0;
            // Find the requests that are made to the user tool.
            $requestIds = $this->guzzleContainer[2 * $i]['request'];
            $requestObjects = $this->guzzleContainer[2 * $i + 1]['request'];

            self::assertEquals("managed_only=$boolBit", $requestIds->getUri()->getQuery());
            self::assertEquals("managed_only=$boolBit", $requestObjects->getUri()->getQuery());
        }
    }

    /**
     * Verifies that no caching is done when no user ID is present in the request. This should be an edge case that's
     * no really possible. But in case it does occur, we want to be sure that definitely no caching is used since
     * then dataset authorization will likely fail.
     */
    public function testMultipleUsersNoUserId()
    {
        // Sanity check; no user ID in request.
        self::assertNull(request()->user_id);

        // Create 2 different fake responses, for 2 different users.
        $response1 = new Response(200, [], '{"datasets":[{"id":7,"name":"Cool dataset",
        "created_at":"2021-03-15T15:02:59.000000Z","updated_at":"2021-03-15T15:02:59.000000Z"},{"id":6,"name":
        "Sjaak & Co","created_at":"2021-03-04T00:42:10.000000Z","updated_at":"2021-03-04T00:42:10.000000Z"},{"id":1,
        "name":"Spotify","created_at":"2020-11-10T17:09:16.000000Z","updated_at":"2020-11-10T17:09:16.000000Z"}]}');
        $response2 = new Response(200, [], '{"datasets":[{"id":6,"name":
        "Sjaak & Co","created_at":"2021-03-04T00:42:10.000000Z","updated_at":"2021-03-04T00:42:10.000000Z"},{"id":1,
        "name":"Spotify","created_at":"2020-11-10T17:09:16.000000Z","updated_at":"2020-11-10T17:09:16.000000Z"}]}');
        $this->mockedResponses = [$response1, $response1, $response2, $response2, $response1, $response1];

        self::assertEquals([1,6,7], $this->repo->getUserDatasetIds()->sort()->values()->all());
        self::assertEquals(
            [1,6,7],
            collect($this->repo->getUserDatasets()->resolve())->pluck('id')->sort()->values()->all()
        );

        self::assertEquals([1,6], $this->repo->getUserDatasetIds()->sort()->values()->all());
        self::assertEquals(
            [1,6],
            collect($this->repo->getUserDatasets()->resolve())->pluck('id')->sort()->values()->all()
        );

        self::assertEquals([1,6,7], $this->repo->getUserDatasetIds()->sort()->values()->all());
        self::assertEquals(
            [1,6,7],
            collect($this->repo->getUserDatasets()->resolve())->pluck('id')->sort()->values()->all()
        );

        // Check that the underlying API has indeed been called 6 times, i.e. no caching was performed.
        self::assertCount(6, $this->guzzleContainer);
    }

    /**
     * Verifies that caching is performed appropriately for different users.
     */
    public function testMultipleUsersWithUserId()
    {
        // Create 2 different fake responses, for 2 different users.
        $response1 = new Response(200, [], '{"datasets":[{"id":7,"name":"Cool dataset",
        "created_at":"2021-03-15T15:02:59.000000Z","updated_at":"2021-03-15T15:02:59.000000Z"},{"id":6,"name":
        "Sjaak & Co","created_at":"2021-03-04T00:42:10.000000Z","updated_at":"2021-03-04T00:42:10.000000Z"},{"id":1,
        "name":"Spotify","created_at":"2020-11-10T17:09:16.000000Z","updated_at":"2020-11-10T17:09:16.000000Z"}]}');
        $response2 = new Response(200, [], '{"datasets":[{"id":6,"name":
        "Sjaak & Co","created_at":"2021-03-04T00:42:10.000000Z","updated_at":"2021-03-04T00:42:10.000000Z"},{"id":1,
        "name":"Spotify","created_at":"2020-11-10T17:09:16.000000Z","updated_at":"2020-11-10T17:09:16.000000Z"}]}');
        $this->mockedResponses = [$response1, $response2];

        request()->user_id = 'user1';
        self::assertEquals([1,6,7], $this->repo->getUserDatasetIds()->sort()->values()->all());
        self::assertEquals(
            [1,6,7],
            collect($this->repo->getUserDatasets()->resolve())->pluck('id')->sort()->values()->all()
        );

        request()->user_id = 'user2';
        self::assertEquals([1,6], $this->repo->getUserDatasetIds()->sort()->values()->all());
        self::assertEquals(
            [1,6],
            collect($this->repo->getUserDatasets()->resolve())->pluck('id')->sort()->values()->all()
        );

        request()->user_id = 'user1';
        self::assertEquals([1,6,7], $this->repo->getUserDatasetIds()->sort()->values()->all());
        self::assertEquals(
            [1,6,7],
            collect($this->repo->getUserDatasets()->resolve())->pluck('id')->sort()->values()->all()
        );

        // Check that the underlying API has been called twice, i.e. the two different responses were cached properly.
        self::assertCount(2, $this->guzzleContainer);
    }

    /**
     * Verifies that caching is performed appropriately for different users, using the managed_only parameter.
     */
    public function testCachingManagedOnly()
    {
        $respEmpty = new Response(200, [], '{"datasets": []}');
        $response1 = new Response(200, [], '{"datasets":[{"id":7,"name":"Cool dataset",
        "created_at":"2021-03-15T15:02:59.000000Z","updated_at":"2021-03-15T15:02:59.000000Z"},{"id":6,"name":
        "Sjaak & Co","created_at":"2021-03-04T00:42:10.000000Z","updated_at":"2021-03-04T00:42:10.000000Z"},{"id":1,
        "name":"Spotify","created_at":"2020-11-10T17:09:16.000000Z","updated_at":"2020-11-10T17:09:16.000000Z"}]}');
        $response2 = new Response(200, [], '{"datasets":[{"id":6,"name":
        "Sjaak & Co","created_at":"2021-03-04T00:42:10.000000Z","updated_at":"2021-03-04T00:42:10.000000Z"},{"id":1,
        "name":"Spotify","created_at":"2020-11-10T17:09:16.000000Z","updated_at":"2020-11-10T17:09:16.000000Z"}]}');
        $response3 = new Response(200, [], '{"datasets":[{"id":1,"name":
        "Spotify","created_at":"2021-03-04T00:42:10.000000Z","updated_at":"2021-03-04T00:42:10.000000Z"}]}');
        $this->mockedResponses = [$response1, $respEmpty, $response2, $response3];

        request()->user_id = 'user1';
        self::assertEquals([1,6,7], $this->repo->getUserDatasetIds()->sort()->values()->all());
        self::assertEquals(
            [1,6,7],
            collect($this->repo->getUserDatasets()->resolve())->pluck('id')->sort()->values()->all()
        );
        self::assertEmpty($this->repo->getUserDatasetIds(true));
        self::assertEmpty($this->repo->getUserDatasets(true));

        request()->user_id = 'user2';
        self::assertEquals([1,6], $this->repo->getUserDatasetIds()->sort()->values()->all());
        self::assertEquals(
            [1,6],
            collect($this->repo->getUserDatasets()->resolve())->pluck('id')->sort()->values()->all()
        );
        self::assertEquals([1], $this->repo->getUserDatasetIds(true)->sort()->values()->all());
        self::assertEquals(
            [1],
            collect($this->repo->getUserDatasets(true)->resolve())->pluck('id')->sort()->values()->all()
        );

        request()->user_id = 'user1';
        self::assertEquals([1,6,7], $this->repo->getUserDatasetIds()->sort()->values()->all());
        self::assertEquals(
            [1,6,7],
            collect($this->repo->getUserDatasets()->resolve())->pluck('id')->sort()->values()->all()
        );
        self::assertEmpty($this->repo->getUserDatasetIds(true));
        self::assertEmpty($this->repo->getUserDatasets(true));

        // Check that the underlying API has been called 4 times, i.e. the different responses were cached properly.
        self::assertCount(4, $this->guzzleContainer);
    }

    /**
     * Verifies that caching can be disabled.
     */
    public function testDisableCaching()
    {
        $respEmpty = new Response(200, [], '{"datasets": []}');
        $response1 = new Response(200, [], '{"datasets":[{"id":7,"name":"Cool dataset",
        "created_at":"2021-03-15T15:02:59.000000Z","updated_at":"2021-03-15T15:02:59.000000Z"},{"id":6,"name":
        "Sjaak & Co","created_at":"2021-03-04T00:42:10.000000Z","updated_at":"2021-03-04T00:42:10.000000Z"},{"id":1,
        "name":"Spotify","created_at":"2020-11-10T17:09:16.000000Z","updated_at":"2020-11-10T17:09:16.000000Z"}]}');
        $response2 = new Response(200, [], '{"datasets":[{"id":6,"name":
        "Sjaak & Co","created_at":"2021-03-04T00:42:10.000000Z","updated_at":"2021-03-04T00:42:10.000000Z"},{"id":1,
        "name":"Spotify","created_at":"2020-11-10T17:09:16.000000Z","updated_at":"2020-11-10T17:09:16.000000Z"}]}');
        $response3 = new Response(200, [], '{"datasets":[{"id":1,"name":
        "Spotify","created_at":"2021-03-04T00:42:10.000000Z","updated_at":"2021-03-04T00:42:10.000000Z"}]}');
        $this->mockedResponses = [$response1, $response1, $respEmpty, $respEmpty, $response2, $response2, $response3,
            $response3, $response1, $response1, $respEmpty, $respEmpty];

        request()->user_id = 'user1';
        self::assertEquals([1,6,7], $this->repo->getUserDatasetIds(false, false)->sort()->values()->all());
        self::assertEquals(
            [1,6,7],
            collect($this->repo->getUserDatasets(false, false)->resolve())->pluck('id')->sort()->values()->all()
        );
        self::assertEmpty($this->repo->getUserDatasetIds(true, false));
        self::assertEmpty($this->repo->getUserDatasets(true, false));

        request()->user_id = 'user2';
        self::assertEquals([1,6], $this->repo->getUserDatasetIds(false, false)->sort()->values()->all());
        self::assertEquals(
            [1,6],
            collect($this->repo->getUserDatasets(false, false)->resolve())->pluck('id')->sort()->values()->all()
        );
        self::assertEquals([1], $this->repo->getUserDatasetIds(true, false)->sort()->values()->all());
        self::assertEquals(
            [1],
            collect($this->repo->getUserDatasets(true, false)->resolve())->pluck('id')->sort()->values()->all()
        );

        request()->user_id = 'user1';
        self::assertEquals([1,6,7], $this->repo->getUserDatasetIds(false, false)->sort()->values()->all());
        self::assertEquals(
            [1,6,7],
            collect($this->repo->getUserDatasets(false, false)->resolve())->pluck('id')->sort()->values()->all()
        );
        self::assertEmpty($this->repo->getUserDatasetIds(true, false));
        self::assertEmpty($this->repo->getUserDatasets(true, false));

        // Check that the underlying API has been called 12 times, i.e. nothing was cached.
        self::assertCount(12, $this->guzzleContainer);
    }

    /**
     * Verifies that the time to live (TTL) of the cache can be configured.
     */
    public function testCachingTtlConfigurable()
    {
        // Set user ID in request to make sure caching is going to be performed.
        request()->user_id = 'user1';

        // Return empty response twice.
        $response = new Response(200, [], '{"datasets": []}');
        $this->mockedResponses = [$response, $response];

        // Set cache TTL to 10 seconds in the config and make sure repo is re-instantiated.
        Config::set('mrd-auth0.cache_ttl', 10);
        $this->repo = App::make(DatasetRepository::class);

        // Call both functions twice.
        $this->repo->getUserDatasets();
        $this->repo->getUserDatasets();
        $this->repo->getUserDatasetIds();
        $this->repo->getUserDatasetIds();

        // Verify only 1 api call was made.
        self::assertCount(1, $this->guzzleContainer);

        // Increment time such that cache TTL should have passed.
        Carbon::setTestNow(Carbon::now()->addSeconds(11));

        // Call functions again and expect a new API call to have been made.
        $this->repo->getUserDatasets();
        $this->repo->getUserDatasets();
        $this->repo->getUserDatasetIds();
        $this->repo->getUserDatasetIds();

        // Verify now a total of api calls has been made.
        self::assertCount(2, $this->guzzleContainer);

        // Increment time such that cache TTL should not have passed.
        Carbon::setTestNow(Carbon::now()->addSeconds(9));

        // Call functions again and expect no new API call to have been made.
        $this->repo->getUserDatasets();
        $this->repo->getUserDatasets();
        $this->repo->getUserDatasetIds();
        $this->repo->getUserDatasetIds();

        // Verify still a total of 2 API calls has been made.
        self::assertCount(2, $this->guzzleContainer);
    }
}
