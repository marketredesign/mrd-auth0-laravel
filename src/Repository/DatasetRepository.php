<?php


namespace Marketredesign\MrdAuth0Laravel\Repository;

use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Marketredesign\MrdAuth0Laravel\Http\Resources\DatasetResource;

class DatasetRepository implements \Marketredesign\MrdAuth0Laravel\Contracts\DatasetRepository
{
    /**
     * @var int Time to store user allowed datasets in cache, in seconds.
     */
    protected int $cacheTTL;

    /**
     * DatasetRepository constructor.
     */
    public function __construct()
    {
        $this->cacheTTL = config('pricecypher.cache_ttl');
    }

    /**
     * Retrieve user datasets by calling the API of user tool directly, without caching.
     *
     * @param bool $managedOnly Only retrieve datasets that the user is a manager of.
     * @return Collection
     * @throws RequestException
     */
    private function retrieveDatasetsFromApi(bool $managedOnly): Collection
    {
        return Http::userTool()->get('/api/datasets', ['managed_only' => $managedOnly])->throw()->collect('datasets');
    }

    /**
     * Get user datasets from cache, when enabled and present, or by retrieving from the API otherwise.
     *
     * @param bool $managedOnly Only get datasets that the user is a manager of.
     * @param bool $cached Use {@code false} to disable retrieving from and storing in cache.
     * @param ?string $guard Name of the auth guard used to get the current user ID. Use {@code null} for default one.
     * @return Collection
     */
    private function getRawDatasets(bool $managedOnly, bool $cached, ?string $guard): Collection
    {
        if (!$cached) {
            return $this->retrieveDatasetsFromApi($managedOnly);
        }

        $userId = Auth::guard($guard)?->id();

        if ($userId == null) {
            // We cannot read from cache since our normal method of retrieving the user ID apparently did not work.
            Log::warning('Unable to find user ID in the request!');
            return $this->retrieveDatasetsFromApi($managedOnly);
        }

        return Cache::remember("datasets-user-$userId-$managedOnly", $this->cacheTTL, function () use ($managedOnly) {
            return $this->retrieveDatasetsFromApi($managedOnly);
        });
    }

    /**
     * @inheritDoc
     */
    public function getUserDatasetIds(
        bool $managedOnly = false,
        bool $cached = true,
        ?string $guard = null,
    ): Collection {
        return $this->getRawDatasets($managedOnly, $cached, $guard)->pluck('id');
    }

    /**
     * @inheritDoc
     */
    public function getUserDatasets(
        bool $managedOnly = false,
        bool $cached = true,
        ?string $guard = null,
    ): ResourceCollection {
        return DatasetResource::collection($this->getRawDatasets($managedOnly, $cached, $guard));
    }
}
