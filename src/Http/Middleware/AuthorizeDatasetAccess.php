<?php

namespace Marketredesign\MrdAuth0Laravel\Http\Middleware;

use App;
use Closure;
use Exception;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Marketredesign\MrdAuth0Laravel\Contracts\DatasetRepository;

class AuthorizeDatasetAccess
{
    /**
     * @var int Time to store user allowed datasets in cache, in seconds.
     */
    protected $cacheTTL;

    /**
     * @var DatasetRepository
     */
    protected $datasetRepo;

    /**
     * AuthorizedDatasetAccess constructor.
     *
     * @param DatasetRepository $datasetRepo
     */
    public function __construct(DatasetRepository $datasetRepo)
    {
        $this->datasetRepo = $datasetRepo;
        $this->cacheTTL = config('mrd-auth0.cache_ttl');
    }

    /**
     * Authorize access to the dataset ID contained in the request.
     *
     * @param Request $request Illuminate HTTP Request object.
     * @param Closure $next Function to call when middleware is complete.
     *
     * @return mixed
     * @throws Exception
     */
    public function handle(Request $request, Closure $next)
    {
        $requestedDatasetId = $this->getRequestedDatasetId($request);

        if ($requestedDatasetId === null) {
            // No dataset ID was found in the request, so no authorization required.
            return $next($request);
        }

        $authorizedDatasets = $this->getAuthorizedDatasetsCached();

        if (!$authorizedDatasets->contains($requestedDatasetId)) {
            abort(403, 'Unauthorized dataset');
        }

        return $next($request);
    }

    /**
     * Retrieve the authorized dataset IDs of the user making the request directly, without caching.
     *
     * @return Collection
     */
    protected function retrieveAuthorizedDatasets(): Collection
    {
        try {
            return $this->datasetRepo->getUserDatasetIds();
        } catch (RequestException $e) {
            Log::error('Unable to request authorized datasets from user tool:');
            Log::error($e);
            abort(401, 'Unable to authorize dataset access.');
        }
    }

    /**
     * Find, and cache, the authorized dataset IDs of the user making the request.
     *
     * @return Collection
     */
    protected function getAuthorizedDatasetsCached(): Collection
    {
        $userId = request()->user_id;

        if ($userId == null) {
            // We cannot read from cache since our normal method of retrieving the user ID apparently did not work.
            Log::warning('Unable to find user ID in the request!');
            return $this->retrieveAuthorizedDatasets();
        }

        return Cache::remember('auth-datasets-' . $userId, $this->cacheTTL, function () {
            return $this->retrieveAuthorizedDatasets();
        });
    }

    /**
     * (Try to) find a dataset ID within the given request.
     *
     * @param Request $request
     * @return null|string
     */
    protected function getRequestedDatasetId(Request $request): ?string
    {
        return $request->input('dataset_id') ?? $request->input('datasetId') ??
            $request->route('dataset_id') ?? $request->route('datasetId');
    }
}
