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
     * @param array $dataset
     * @param string $name
     * @param string $createdAt
     * @param string $updatedAt
     */
    protected function assertDataset(array $dataset, string $name, string $createdAt, string $updatedAt)
    {
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
}
