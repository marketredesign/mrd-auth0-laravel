<?php

namespace Marketredesign\MrdAuth0Laravel\Tests\Feature;

use Marketredesign\MrdAuth0Laravel\Contracts\DatasetRepository;
use Marketredesign\MrdAuth0Laravel\Facades\Datasets;
use Marketredesign\MrdAuth0Laravel\Tests\TestCase;

class DatasetFacadeTest extends TestCase
{
    /**
     * Verifies that the getUserDatasetIds method is executed by our DatasetRepository implementation.
     */
    public function testGetUserDatasetIds()
    {
        $this->mock(DatasetRepository::class, function ($mock) {
            $mock->shouldReceive('getUserDatasetIds')->once()->withNoArgs();
        });

        Datasets::getUserDatasetIds();
    }

    /**
     * Verifies that the getUserDatasets method is executed by our DatasetRepository implementation.
     */
    public function testGetUserDatasets()
    {
        $this->mock(DatasetRepository::class, function ($mock) {
            $mock->shouldReceive('getUserDatasets')->once()->withNoArgs();
        });

        Datasets::getUserDatasets();
    }

    /**
     * Verifies testing mode with adding fake datasets by collection of IDs.
     */
    public function testFakeAddDatasetsById()
    {
        // Enable testing mode.
        Datasets::fake();

        // Initially expect no dataset access.
        self::assertTrue(Datasets::getUserDatasetIds()->isEmpty());
        self::assertEmpty(Datasets::getUserDatasets()->resolve());

        // Add 2 fake datasets to the repository.
        Datasets::fakeAddDatasets(collect([10, 3]));

        // Retrieve dataset access.
        $datasetIds = Datasets::getUserDatasetIds();
        $datasets = collect(Datasets::getUserDatasets()->resolve());

        // Verify correct number of datasets.
        self::assertEquals(2, $datasetIds->count());
        self::assertEquals(2, $datasets->count());

        // Verify correct dataset IDs returned.
        self::assertContains(10, $datasetIds);
        self::assertContains(3, $datasetIds);

        // Find datasets in response.
        $dataset10 = $datasets->firstWhere('id', 10);
        $dataset3 = $datasets->firstWhere('id', 3);

        // Verify the correct datasets are returned.
        self::assertNotNull($dataset10);
        self::assertNotNull($dataset3);

        // Verify the returned datasets have fake data.
        foreach ([$dataset10, $dataset3] as $dataset) {
            self::assertNotEmpty($dataset['name']);
            self::assertNotEmpty($dataset['created_at']);
            self::assertNotEmpty($dataset['updated_at']);
            self::assertArrayHasKey('modules', $dataset);
            self::assertNotNull($dataset['modules']);
        }

        // Verify no managed datasets.
        self::assertEmpty(Datasets::getUserDatasetIds(true));
        self::assertEmpty(Datasets::getUserDatasets(true));
    }

    /**
     * Verifies testing mode with adding fake datasets by collection of collectionss.
     */
    public function testFakeAddDatasetsByCollections()
    {
        // Enable testing mode.
        Datasets::fake();

        // Initially expect no dataset access.
        self::assertTrue(Datasets::getUserDatasetIds()->isEmpty());
        self::assertEmpty(Datasets::getUserDatasets()->resolve());

        // Add 3 fake datasets to the repository with varying levels of filled fields.
        Datasets::fakeAddDatasets(collect([
            collect([
                'id' => 10,
            ]),
            collect([
                'id' => 5,
                'name' => 'A Dataset with ID 5',
                'modules' => [],
            ]),
            collect([
                'id' => 8,
                'name' => 'Dataset #8',
                'dss_url' => 'https://website.com',
                'created_at' => '2022-05-17T15:02:59.000000Z',
                'updated_at' => '2022-05-17T15:02:59.000000Z',
                'modules' => ['module_A', 'another_module'],
            ]),
        ]));

        // Retrieve dataset access.
        $datasetIds = Datasets::getUserDatasetIds();
        $datasets = collect(Datasets::getUserDatasets()->resolve());

        // Verify correct number of datasets.
        self::assertEquals(3, $datasetIds->count());
        self::assertEquals(3, $datasets->count());

        // Verify correct dataset IDs returned.
        self::assertContains(10, $datasetIds);
        self::assertContains(5, $datasetIds);
        self::assertContains(8, $datasetIds);

        // Find datasets in response.
        $dataset10 = $datasets->firstWhere('id', 10);
        $dataset5 = $datasets->firstWhere('id', 5);
        $dataset8 = $datasets->firstWhere('id', 8);

        // Verify the correct datasets are returned.
        self::assertNotNull($dataset10);
        self::assertNotNull($dataset5);
        self::assertNotNull($dataset8);

        // Verify the returned datasets have fake data.
        foreach ([$dataset10, $dataset5, $dataset8] as $dataset) {
            self::assertNotEmpty($dataset['name']);
            self::assertNotEmpty($dataset['created_at']);
            self::assertNotEmpty($dataset['updated_at']);
            self::assertArrayHasKey('modules', $dataset);
            self::assertNotNull($dataset['modules']);
        }

        // Verify the inputted fields are present in the response
        self::assertEquals('A Dataset with ID 5', $dataset5['name']);
        self::assertEquals([], $dataset5['modules']);
        self::assertEquals('Dataset #8', $dataset8['name']);
        self::assertEquals('https://website.com', $dataset8['dss_url']);
        self::assertEquals('2022-05-17T15:02:59.000000Z', $dataset8['created_at']);
        self::assertEquals('2022-05-17T15:02:59.000000Z', $dataset8['updated_at']);
        self::assertEquals(['module_A', 'another_module'], $dataset8['modules']);

        // Verify no managed datasets.
        self::assertEmpty(Datasets::getUserDatasetIds(true));
        self::assertEmpty(Datasets::getUserDatasets(true));
    }

    /**
     * Verifies that, in testing mode, requesting the same datasets twice results in the same dataset objects being
     * returned.
     */
    public function testFakeRequestingSameDatasetsTwice()
    {
        // Enable testing mode.
        Datasets::fake();

        // Add 2 fake datasets to the repository by IDs.
        Datasets::fakeAddDatasets(collect([10, 3]));

        // Add 2 fake datasets to the repository by collections.
        Datasets::fakeAddDatasets(collect([
            collect(['id' => 8]),
            collect(['id' => 12, 'name' => 'A dataset']),
        ]));

        // Retrieve datasets twice
        $datasets1 = collect(Datasets::getUserDatasets()->resolve());
        $datasets2 = collect(Datasets::getUserDatasets()->resolve());

        // Verify the dataset objects returned are the same.
        self::assertNotNull($datasets1);
        self::assertEquals($datasets1, $datasets2);
    }

    /**
     * Verifies that the fake repository can be cleared.
     */
    public function testFakeClear()
    {
        // Enable testing mode.
        Datasets::fake();

        // Add fake datasets to the repository by ID and collection.
        Datasets::fakeAddDatasets(collect([9]));
        Datasets::fakeAddDatasets(collect([collect(['id' => 10])]));
        // Add fake managed datasets to the repository by ID and collection.
        Datasets::fakeAddDatasets(collect([12]), true);
        Datasets::fakeAddDatasets(collect([collect(['id' => 13])]), true);

        // Verify datasets exist.
        self::assertContains(9, Datasets::getUserDatasetIds());
        self::assertContains(10, Datasets::getUserDatasetIds());
        self::assertContains(12, Datasets::getUserDatasetIds(true));
        self::assertEquals(9, Datasets::getUserDatasets()->resolve()[0]['id']);
        self::assertEquals(10, Datasets::getUserDatasets()->resolve()[1]['id']);
        self::assertEquals(12, Datasets::getUserDatasets()->resolve()[2]['id']);
        self::assertEquals(13, Datasets::getUserDatasets()->resolve()[3]['id']);
        self::assertEquals(12, Datasets::getUserDatasets(true)->resolve()[0]['id']);
        self::assertEquals(13, Datasets::getUserDatasets(true)->resolve()[1]['id']);

        // Now clear the repository.
        Datasets::fakeClear();

        // Verify datasets do not exist anymore.
        self::assertTrue(Datasets::getUserDatasetIds()->isEmpty());
        self::assertTrue(Datasets::getUserDatasetIds(true)->isEmpty());
        self::assertEmpty(Datasets::getUserDatasets()->resolve());
        self::assertEmpty(Datasets::getUserDatasets(true)->resolve());
    }

    /**
     * Verifies that the number of datasets in the fake repository can be counted.
     */
    public function testCount()
    {
        // Enable testing mode.
        Datasets::fake();

        // Add 3 fake datasets to the repository.
        Datasets::fakeAddDatasets(collect([4, 8]));
        Datasets::fakeAddDatasets(collect([collect(['id' => 12, 'name' => 'Sjaak'])]));
        // Add 2 additional datasets which the user is manager for.
        Datasets::fakeAddDatasets(collect([5]), true);
        Datasets::fakeAddDatasets(collect([collect(['id' => 99, 'modules' => ['some_module']])]), true);

        // Verify count.
        self::assertEquals(5, Datasets::fakeCount());
        self::assertEquals(2, Datasets::fakeCount(true));

        // Now clear the repository.
        Datasets::fakeClear();

        // Verify count zero.
        self::assertEquals(0, Datasets::fakeCount());
        self::assertEquals(0, Datasets::fakeCount(true));
    }
}
