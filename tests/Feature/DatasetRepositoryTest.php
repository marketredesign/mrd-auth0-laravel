<?php


namespace Marketredesign\MrdAuth0Laravel\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Marketredesign\MrdAuth0Laravel\Contracts\DatasetRepository;
use Marketredesign\MrdAuth0Laravel\Facades\PricecypherAuth;
use Marketredesign\MrdAuth0Laravel\Tests\TestCase;

class DatasetRepositoryTest extends TestCase
{
    use WithFaker;

    protected const BASE_USERS = 'https://datasets.test';

    /**
     * @var DatasetRepository
     */
    protected $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = App::make(DatasetRepository::class);

        Http::preventStrayRequests();
        PricecypherAuth::fake();
    }

    /**
     * Define environment setup.
     *
     * @param Application $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        // Set the Laravel Auth0 config values which are used to some values.
        $app['config']->set('pricecypher.services.user_tool', self::BASE_USERS);
    }

    protected function ds(int $id, array $modules = []): array
    {
        return [
            'id' => $id,
            'modules' => $modules,
            'name' => $this->faker()->name,
            'dss_url' => $this->faker()->url,
            'created_at' => Carbon::create($this->faker()->dateTime)->toISOString(),
            'updated_at' => Carbon::create($this->faker()->dateTime)->toISOString(),
        ];
    }

    /**
     * Asserts that the given dataset is as expected.
     *
     * @param array $expDataset
     * @param array|null $actDataset
     */
    protected function assertDataset(array $expDataset, ?array $actDataset): void
    {
        self::assertNotNull($actDataset);

        self::assertEquals($expDataset['name'], $actDataset['name']);
        self::assertEquals($expDataset['dss_url'], $actDataset['dss_url']);

        $actCreatedAt = Carbon::parse($actDataset['created_at']);
        $actUpdatedAt = Carbon::parse($actDataset['updated_at']);

        self::assertTrue(Carbon::parse($expDataset['created_at'])->equalTo($actCreatedAt));
        self::assertTrue(Carbon::parse($expDataset['updated_at'])->equalTo($actUpdatedAt));
    }

    protected function assertRequestsCount(int $expectedCount, ?callable $extraRequestFilter = null): void
    {
        $filterPredicate = fn(Request $req) => Str::startsWith($req->url(), self::BASE_USERS);

        if ($extraRequestFilter) {
            $filterPredicate = fn(Request $req) => $filterPredicate($req) && $extraRequestFilter($req);
        }

        self::assertCount($expectedCount, Http::recorded($filterPredicate));
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
        foreach (['https://users.com', 'https://users.pricecypher.com', 'http://tests.pc.test/api'] as $baseUrl) {
            Config::set('pricecypher.services.user_tool', $baseUrl);
            Http::fake(["$baseUrl/api/datasets?*" => Http::response(['datasets' => []])]);

            // Call function under test.
            $this->repo->getUserDatasetIds();

            // Expect number of api calls to increment each time by one.
            self::assertCount(1, Http::recorded(fn(Request $req) => Str::startsWith($req->url(), $baseUrl)));
        }
    }

    /**
     * Verifies that an empty collection of dataset IDs is returned when the user has access to no datasets.
     */
    public function testGetIdsNoDatasets()
    {
        // Return empty response
        Http::fake([self::BASE_USERS . '/api/datasets?*' => Http::response(['datasets' => []])]);

        // Call function under test.
        $datasetIds = $this->repo->getUserDatasetIds();

        // Verify response indeed empty.
        self::assertNotNull($datasetIds);
        self::assertTrue($datasetIds->isEmpty());

        // Expect 1 api call, verifying whether the correct endpoint was called.
        $this->assertRequestsCount(
            1,
            fn(Request $req) => Str::startsWith($req->url(), self::BASE_USERS . '/api/datasets?'),
        );
    }


    /**
     * Verifies that an empty collection of datasets is returned when the user has access to no datasets.
     */
    public function testGetDatasetsNoDatasets()
    {
        // Return empty response
        Http::fake([self::BASE_USERS . '/api/datasets?*' => Http::response(['datasets' => []])]);

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
        Http::fake([self::BASE_USERS . '/api/datasets?*' => Http::response(['datasets' => [
            $this->ds(7), $this->ds(6, ['module_A']), $this->ds(1)
        ]])]);

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
        $ds1 = $this->ds(1, ['module_B', 'module_C']);
        $ds6 = $this->ds(6, ['module_A', 'module_B']);
        $ds7 = $this->ds(7);
        // Return mocked response as given by user tool.
        Http::fake([self::BASE_USERS . '/api/datasets?*' => Http::response(['datasets' => [$ds7, $ds6, $ds1]])]);

        // Call function under test.
        $resourceCollection = $this->repo->getUserDatasets();
        // Resolve the resource collection, such that we can verify its contents.
        $datasets = collect($resourceCollection->resolve());

        // Verify response contains exactly 3 datasets.
        self::assertNotNull($datasets);
        self::assertEquals(3, $datasets->count());

        // Find datasets by ID in the response.
        $actDs1 = $datasets->firstWhere('id', 1);
        $actDs6 = $datasets->firstWhere('id', 6);
        $actDs7 = $datasets->firstWhere('id', 7);

        // Verify that datasets are as expected.
        $this->assertDataset($ds1, $actDs1);
        $this->assertDataset($ds6, $actDs6);
        $this->assertDataset($ds7, $actDs7);
    }

    /**
     * Verifies that the bearer token is used in the request to the user tool.
     */
    public function testBearerTokenInRequest()
    {
        // Return empty response 3 times.
        Http::fake([self::BASE_USERS . '/api/datasets?*' => Http::response(['datasets' => []])]);

        foreach (['token', 'eyaskjfasas', 'myVerycooltokensTrinGthatisActuallYNojwT'] as $token) {
            // We set the header as part of the request that is currently "being handled the framework".
            PricecypherAuth::fakeSetM2mAccessToken($token);

            // Call function under test.
            $this->repo->getUserDatasets();

            // Find the request that is made to the user tool. NB: this is a different request than above.
            Http::assertSent(fn(Request $req) => Str::startsWith($req->url(), self::BASE_USERS)
                && $req->hasHeader('Authorization', "Bearer $token"));
        }
    }

    /**
     * Verifies that the `managed_only` query parameter is sent to the user tool when requesting datasets (or IDs).
     */
    public function testManagedOnlyParameter()
    {
        // Return empty response 4 times.
        Http::fake([self::BASE_USERS . '/api/datasets?*' => Http::response(['datasets' => []])]);

        foreach ([false, true] as $bool) {
            $boolBit = $bool ? 1 : 0;
            $checkQueryParam = fn(Request $req) => Str::contains($req->url(), "managed_only=$boolBit");

            $this->assertRequestsCount(0, $checkQueryParam);

            // Call functions under test.
            $this->repo->getUserDatasetIds($bool);
            $this->assertRequestsCount(1, $checkQueryParam);

            $this->repo->getUserDatasets($bool);
            $this->assertRequestsCount(2, $checkQueryParam);
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
        self::assertNull(\Illuminate\Support\Facades\Auth::id());

        $ds1 = $this->ds(1, ['module_B', 'module_C']);
        $ds6 = $this->ds(6, ['module_A', 'module_B']);
        $ds7 = $this->ds(7);
        // Create 2 different fake responses, for 2 different users.
        $response1 = Http::response(['datasets' => [$ds7, $ds6, $ds1]]);
        $response2 = Http::response(['datasets' => [$ds6, $ds1]]);
        Http::fake([self::BASE_USERS . '/api/datasets?*' => Http::sequence([$response1, $response1, $response2, $response2, $response1, $response1])]);

        self::assertEquals([1, 6, 7], $this->repo->getUserDatasetIds()->sort()->values()->all());
        self::assertEquals(
            [1, 6, 7],
            collect($this->repo->getUserDatasets()->resolve())->pluck('id')->sort()->values()->all()
        );

        self::assertEquals([1, 6], $this->repo->getUserDatasetIds()->sort()->values()->all());
        self::assertEquals(
            [1, 6],
            collect($this->repo->getUserDatasets()->resolve())->pluck('id')->sort()->values()->all()
        );

        self::assertEquals([1, 6, 7], $this->repo->getUserDatasetIds()->sort()->values()->all());
        self::assertEquals(
            [1, 6, 7],
            collect($this->repo->getUserDatasets()->resolve())->pluck('id')->sort()->values()->all()
        );

        // Check that the underlying API has indeed been called 6 times, i.e. no caching was performed.
        $this->assertRequestsCount(6);
    }

    /**
     * Verifies that caching is performed appropriately for different users.
     */
    public function testMultipleUsersWithUserId()
    {
        // Create 2 different fake responses, for 2 different users.
        $ds1 = $this->ds(1, ['module_B', 'module_C']);
        $ds6 = $this->ds(6, ['module_A', 'module_B']);
        $ds7 = $this->ds(7);
        Http::fake([self::BASE_USERS . '/api/datasets?*' => Http::sequence()
            ->push(['datasets' => [$ds7, $ds6, $ds1]])
            ->push(['datasets' => [$ds6, $ds1]])]);

        $this->auth(['sub' => 'user1']);
        self::assertEquals([1, 6, 7], $this->repo->getUserDatasetIds()->sort()->values()->all());
        self::assertEquals(
            [1, 6, 7],
            collect($this->repo->getUserDatasets()->resolve())->pluck('id')->sort()->values()->all()
        );

        $this->auth(['sub' => 'user2']);
        self::assertEquals([1, 6], $this->repo->getUserDatasetIds()->sort()->values()->all());
        self::assertEquals(
            [1, 6],
            collect($this->repo->getUserDatasets()->resolve())->pluck('id')->sort()->values()->all()
        );

        $this->auth(['sub' => 'user1']);
        self::assertEquals([1, 6, 7], $this->repo->getUserDatasetIds()->sort()->values()->all());
        self::assertEquals(
            [1, 6, 7],
            collect($this->repo->getUserDatasets()->resolve())->pluck('id')->sort()->values()->all()
        );

        // Check that the underlying API has been called twice, i.e. the two different responses were cached properly.
        $this->assertRequestsCount(2);
    }

    /**
     * Verifies that caching is performed appropriately for different users, using the managed_only parameter.
     */
    public function testCachingManagedOnly()
    {
        $ds1 = $this->ds(1, ['module_B', 'module_C']);
        $ds6 = $this->ds(6, ['module_A', 'module_B']);
        $ds7 = $this->ds(7);
        Http::fake([self::BASE_USERS . '/api/datasets?*' => Http::sequence()
            ->push(['datasets' => [$ds7, $ds6, $ds1]])
            ->push(['datasets' => []])
            ->push(['datasets' => [$ds6, $ds1]])
            ->push(['datasets' => [$ds1]])]);

        $this->auth(['sub' => 'user1']);
        self::assertEquals([1, 6, 7], $this->repo->getUserDatasetIds()->sort()->values()->all());
        self::assertEquals(
            [1, 6, 7],
            collect($this->repo->getUserDatasets()->resolve())->pluck('id')->sort()->values()->all()
        );
        self::assertEmpty($this->repo->getUserDatasetIds(true));
        self::assertEmpty($this->repo->getUserDatasets(true));

        $this->auth(['sub' => 'user2']);
        self::assertEquals([1, 6], $this->repo->getUserDatasetIds()->sort()->values()->all());
        self::assertEquals(
            [1, 6],
            collect($this->repo->getUserDatasets()->resolve())->pluck('id')->sort()->values()->all()
        );
        self::assertEquals([1], $this->repo->getUserDatasetIds(true)->sort()->values()->all());
        self::assertEquals(
            [1],
            collect($this->repo->getUserDatasets(true)->resolve())->pluck('id')->sort()->values()->all()
        );

        $this->auth(['sub' => 'user1']);
        self::assertEquals([1, 6, 7], $this->repo->getUserDatasetIds()->sort()->values()->all());
        self::assertEquals(
            [1, 6, 7],
            collect($this->repo->getUserDatasets()->resolve())->pluck('id')->sort()->values()->all()
        );
        self::assertEmpty($this->repo->getUserDatasetIds(true));
        self::assertEmpty($this->repo->getUserDatasets(true));

        // Check that the underlying API has been called 4 times, i.e. the different responses were cached properly.
        $this->assertRequestsCount(4);
    }

    /**
     * Verifies that caching can be disabled.
     */
    public function testDisableCaching()
    {
        $ds1 = $this->ds(1, ['module_B', 'module_C']);
        $ds6 = $this->ds(6, ['module_A', 'module_B']);
        $ds7 = $this->ds(7);
        $response1 = Http::response(['datasets' => [$ds7, $ds6, $ds1]]);
        $response2 = Http::response(['datasets' => [$ds6, $ds1]]);
        $response3 = Http::response(['datasets' => [$ds1]]);
        $respEmpty = Http::response(['datasets' => []]);
        Http::fake([self::BASE_USERS . '/api/datasets?*' => Http::sequence([$response1, $response1, $respEmpty, $respEmpty, $response2, $response2, $response3,
            $response3, $response1, $response1, $respEmpty, $respEmpty])]);

        $this->auth(['sub' => 'user1']);
        self::assertEquals([1, 6, 7], $this->repo->getUserDatasetIds(false, false)->sort()->values()->all());
        self::assertEquals(
            [1, 6, 7],
            collect($this->repo->getUserDatasets(false, false)->resolve())->pluck('id')->sort()->values()->all()
        );
        self::assertEmpty($this->repo->getUserDatasetIds(true, false));
        self::assertEmpty($this->repo->getUserDatasets(true, false));

        $this->auth(['sub' => 'user2']);
        self::assertEquals([1, 6], $this->repo->getUserDatasetIds(false, false)->sort()->values()->all());
        self::assertEquals(
            [1, 6],
            collect($this->repo->getUserDatasets(false, false)->resolve())->pluck('id')->sort()->values()->all()
        );
        self::assertEquals([1], $this->repo->getUserDatasetIds(true, false)->sort()->values()->all());
        self::assertEquals(
            [1],
            collect($this->repo->getUserDatasets(true, false)->resolve())->pluck('id')->sort()->values()->all()
        );

        $this->auth(['sub' => 'user1']);
        self::assertEquals([1, 6, 7], $this->repo->getUserDatasetIds(false, false)->sort()->values()->all());
        self::assertEquals(
            [1, 6, 7],
            collect($this->repo->getUserDatasets(false, false)->resolve())->pluck('id')->sort()->values()->all()
        );
        self::assertEmpty($this->repo->getUserDatasetIds(true, false));
        self::assertEmpty($this->repo->getUserDatasets(true, false));

        // Check that the underlying API has been called 12 times, i.e. nothing was cached.
        $this->assertRequestsCount(12);
    }

    /**
     * Verifies that the time to live (TTL) of the cache can be configured.
     */
    public function testCachingTtlConfigurable()
    {
        // Set user ID in request to make sure caching is going to be performed.
        $this->auth(['sub' => 'user1']);

        // Return empty response twice.
        Http::fake([self::BASE_USERS . '/api/datasets?*' => Http::response(['datasets' => []])]);

        // Set cache TTL to 10 seconds in the config and make sure repo is re-instantiated.
        Config::set('pricecypher.cache_ttl', 10);
        $this->repo = App::make(DatasetRepository::class);

        // Call both functions twice.
        $this->repo->getUserDatasets();
        $this->repo->getUserDatasets();
        $this->repo->getUserDatasetIds();
        $this->repo->getUserDatasetIds();

        // Verify only 1 api call was made.
        $this->assertRequestsCount(1);

        // Increment time such that cache TTL should have passed.
        Carbon::setTestNow(Carbon::now()->addSeconds(11));

        // Call functions again and expect a new API call to have been made.
        $this->repo->getUserDatasets();
        $this->repo->getUserDatasets();
        $this->repo->getUserDatasetIds();
        $this->repo->getUserDatasetIds();

        // Verify now a total of api calls has been made.
        $this->assertRequestsCount(2);

        // Increment time such that cache TTL should not have passed.
        Carbon::setTestNow(Carbon::now()->addSeconds(9));

        // Call functions again and expect no new API call to have been made.
        $this->repo->getUserDatasets();
        $this->repo->getUserDatasets();
        $this->repo->getUserDatasetIds();
        $this->repo->getUserDatasetIds();

        // Verify still a total of 2 API calls has been made.
        $this->assertRequestsCount(2);
    }
}
