<?php

namespace Marketredesign\MrdAuth0Laravel\Repository\Fakes;

use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;
use Marketredesign\MrdAuth0Laravel\Contracts\DatasetRepository;
use Marketredesign\MrdAuth0Laravel\Http\Resources\DatasetResource;

class FakeDatasetRepository implements DatasetRepository
{
    use WithFaker;

    /** @var Collection Fake dataset IDs that the user has access to. */
    private Collection $datasetIds;

    /** @var Collection Fake dataset IDs that the user is manager of. */
    private Collection $managedDatasetIds;

    /** @var Collection Fake dataset objects, keyed by dataset ID. */
    private Collection $datasets;

    public function __construct()
    {
        $this->fakeClear();
    }

    /**
     * @param  bool  $managedOnly  Only count datasets that the user is manager of.
     * @return int Count number of datasets in the fake dataset repository.
     */
    public function fakeCount(bool $managedOnly = false): int
    {
        if ($managedOnly) {
            return $this->managedDatasetIds->count();
        } else {
            return $this->datasetIds->count();
        }
    }

    /**
     * Clear fake user repository.
     */
    public function fakeClear(): void
    {
        $this->datasetIds = collect();
        $this->managedDatasetIds = collect();
        $this->datasets = collect();
        $this->setUpFaker();
    }

    /**
     * Add datasets.
     *
     * @param  Collection  $datasets  Datasets the user has access to. Either a collection of dataset IDs to add,
     *                                or a collection of collections each containing at least an 'id' and optionally extra fields.
     *                                For any non-provided fields (or all non-id fields when sending a collection of IDs), a random value will be used.
     * @param  bool  $isManager  {@code true} iff the user is manager of the given datasets.
     */
    public function fakeAddDatasets(Collection $datasets, bool $isManager = false): void
    {
        // Convert plain dataset IDs to dataset collections with an ID
        $datasets = $datasets->map(fn ($ds) => is_numeric($ds) ? collect(['id' => $ds]) : $ds);

        // Create the datasets
        $datasets->each(fn ($ds) => $this->createDataset($ds));

        // Add the IDs to the allowed IDs, including managed IDs if applicable
        $this->datasetIds = $this->datasetIds->concat($datasets->pluck('id')->all())->unique();
        if ($isManager) {
            $this->managedDatasetIds = $this->managedDatasetIds->concat($datasets->pluck('id')->all())->unique();
        }
    }

    /**
     * Create and store a dataset. Any fields that are not provided are generated randomly.
     *
     * @param  $dataset  Collection Dataset to add, including at least the 'id' field.
     */
    private function createDataset(Collection $dataset): void
    {
        // Construct some (or 0) fake modules
        $fakeModules = $this->faker->words($this->faker->randomDigit());

        // Add a dataset with these fields, prioritizing provided data
        $dsObject = [
            'id' => $dataset->get('id'),
            'name' => $dataset->get('name', $this->faker->firstName),
            'dss_url' => $dataset->get('dss_url', $this->faker->url),
            'created_at' => $dataset->get('created_at', $this->faker->dateTime),
            'updated_at' => $dataset->get('updated_at', $this->faker->dateTime),
            'modules' => $dataset->get('modules', $fakeModules),
        ];
        $this->datasets->put($dsObject['id'], $dsObject);
    }

    /**
     * {@inheritDoc}
     */
    public function getUserDatasetIds(
        bool $managedOnly = false,
        bool $cached = true,
        ?string $guard = null,
    ): Collection {
        if ($managedOnly) {
            return $this->managedDatasetIds;
        } else {
            return $this->datasetIds;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getUserDatasets(
        bool $managedOnly = false,
        bool $cached = true,
        ?string $guard = null,
    ): ResourceCollection {
        $datasets = $this->getUserDatasetIds($managedOnly)->map(function ($datasetId) {
            return $this->datasets->get($datasetId);
        });

        return DatasetResource::collection($datasets);
    }
}
