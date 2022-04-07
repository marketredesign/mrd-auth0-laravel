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

    /**
     * @var Collection Fake dataset IDs that the user has access to.
     */
    private $datasetIds;

    /**
     * @var Collection Fake dataset IDs that the user is manager of.
     */
    private $managedDatasetIds;

    /**
     * @var Collection Fake dataset objects, keyed by dataset ID.
     */
    private $datasets;

    public function __construct()
    {
        $this->fakeClear();
    }

    /**
     * @param bool $managedOnly Only count datasets that the user is manager of.
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
     * Add collection of dataset IDs for which a random dataset object will be returned when the repository is queried.
     *
     * @param Collection $ids Dataset IDs the user has access to.
     * @param bool $isManager Whether or not the user is manager of the given datasets.
     */
    public function fakeAddDatasets(Collection $ids, bool $isManager = false): void
    {
        $this->datasetIds = $this->datasetIds->concat($ids)->unique();

        if ($isManager) {
            $this->managedDatasetIds = $this->managedDatasetIds->concat($ids)->unique();
        }
    }

    /**
     * Gets the dataset object for the given ID or creates, stores and returns a random one if it doesn't already exist.
     *
     * @param $id int Dataset ID to get the object for.
     * @return object Dataset object
     */
    private function getOrCreateDatasetForId(int $id): object
    {
        if ($this->datasets->has($id)) {
            return $this->datasets->get($id);
        }

        $dataset = (object)[
            'id' => $id,
            'name' => $this->faker->firstName,
            'created_at' => $this->faker->dateTime,
            'updated_at' => $this->faker->dateTime,
        ];

        $this->datasets->put($id, $dataset);

        return $dataset;
    }

    /**
     * @inheritDoc
     */
    public function getUserDatasetIds(bool $managedOnly = false, bool $cached = true): Collection
    {
        if ($managedOnly) {
            return $this->managedDatasetIds;
        } else {
            return $this->datasetIds;
        }
    }

    /**
     * @inheritDoc
     */
    public function getUserDatasets(bool $managedOnly = false, bool $cached = true): ResourceCollection
    {
        $datasets = $this->getUserDatasetIds($managedOnly)->map(function ($datasetId) {
            return $this->getOrCreateDatasetForId($datasetId);
        });

        return DatasetResource::collection($datasets);
    }
}
