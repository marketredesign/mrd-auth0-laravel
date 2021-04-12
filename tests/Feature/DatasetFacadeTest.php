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
     * Verifies testing mode with adding fake datasets.
     */
    public function testFakeAddDatasets()
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
        }
    }

    /**
     * Verifies that, in testing mode, requesting the same datasets twice results in the same dataset objects being
     * returned.
     */
    public function testFakeRequestingSameDatasetsTwice()
    {
        // Enable testing mode.
        Datasets::fake();

        // Add 2 fake datasets to the repository.
        Datasets::fakeAddDatasets(collect([10, 3]));

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

        // Add fake dataset to the repository.
        Datasets::fakeAddDatasets(collect([10]));

        // Verify dataset exists.
        self::assertContains(10, Datasets::getUserDatasetIds());
        self::assertEquals(10, Datasets::getUserDatasets()->resolve()[0]['id']);

        // Now clear the repository.
        Datasets::fakeClear();

        // Verify dataset does not exist anymore.
        self::assertTrue(Datasets::getUserDatasetIds()->isEmpty());
        self::assertEmpty(Datasets::getUserDatasets()->resolve());
    }

    /**
     * Verifies that the number of datasets in the fake repository can be counted.
     */
    public function testCount()
    {
        // Enable testing mode.
        Datasets::fake();

        // Add 3 fake datasets to the repository.
        Datasets::fakeAddDatasets(collect([4, 8, 12]));

        // Verify count.
        self::assertEquals(3, Datasets::fakeCount());

        // Now clear the repository.
        Datasets::fakeClear();

        // Verify count zero.
        self::assertEquals(0, Datasets::fakeCount());
    }
}
