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
     * @var Collection Fake dataset objects, keyed by dataset ID.
     */
    private $datasets;

    public function __construct()
    {
        $this->fakeClear();
    }

    /**
     * @return int Count number of datasets in the fake dataset repository.
     */
    public function fakeCount(): int
    {
        return $this->datasetIds->count();
    }

    /**
     * Clear fake user repository.
     */
    public function fakeClear(): void
    {
        $this->datasetIds = collect();
        $this->datasets = collect();
        $this->setUpFaker();
    }

    /**
     * Add collection of dataset IDs for which a random dataset object will be returned when the repository is queried.
     *
     * @param Collection $ids Dataset IDs the user has access to.
     */
    public function fakeAddDatasets(Collection $ids): void
    {
        $this->datasetIds = $this->datasetIds->concat($ids)->unique();
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
    public function getUserDatasetIds(): Collection
    {
        return $this->datasetIds;
    }

    /**
     * @inheritDoc
     */
    public function getUserDatasets(): ResourceCollection
    {
        $datasets = $this->datasetIds->map(function ($datasetId) {
            return $this->getOrCreateDatasetForId($datasetId);
        });

        return DatasetResource::collection($datasets);
    }
}
