<?php


namespace Marketredesign\MrdAuth0Laravel\Contracts;

use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;

interface DatasetRepository
{
    /**
     * Get all dataset IDs that the user in the current request has access to.
     *
     * @return Collection of dataset IDs.
     * @throws RequestException
     */
    public function getUserDatasetIds(): Collection;

    /**
     * Get all datasets that the user in the current request has access to.
     *
     * @return ResourceCollection of datasets.
     * @throws RequestException
     */
    public function getUserDatasets(): ResourceCollection;
}
