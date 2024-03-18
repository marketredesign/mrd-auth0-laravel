<?php


namespace Marketredesign\MrdAuth0Laravel\Contracts;

use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;

interface DatasetRepository
{
    /**
     * Get all dataset IDs that the user in the current request has access to.
     *
     * @param bool $managedOnly Only retrieve IDs of datasets that the user is a manager of. Defaults to {@code false}.
     * @param bool $cached Use {@code false} to disable retrieving from and storing in cache. Defaults to {@code true}.
     * @param ?string $guard Name of the auth guard used to get the current user ID. Defaults to the default guard.
     * @return Collection of dataset IDs.
     * @throws RequestException
     */
    public function getUserDatasetIds(
        bool $managedOnly = false,
        bool $cached = true,
        ?string $guard = null,
    ): Collection;

    /**
     * Get all datasets that the user in the current request has access to.
     *
     * @param bool $managedOnly Only retrieve datasets that the user is a manager of. Defaults to {@code false}.
     * @param bool $cached Use {@code false} to disable retrieving from and storing in cache. Defaults to {@code true}.
     * @param ?string $guard Name of the auth guard used to get the current user ID. Defaults to the default guard.
     * @return ResourceCollection of datasets.
     * @throws RequestException
     */
    public function getUserDatasets(
        bool $managedOnly = false,
        bool $cached = true,
        ?string $guard = null,
    ): ResourceCollection;
}
