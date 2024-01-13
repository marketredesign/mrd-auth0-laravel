<?php

namespace Marketredesign\MrdAuth0Laravel\Http\Middleware;

use App;
use Closure;
use Exception;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Marketredesign\MrdAuth0Laravel\Facades\Datasets;

class AuthorizeDatasetAccess
{
    /**
     * @var array List of supported keys to represent the dataset ID in the requests (query/body/route).
     */
    protected const SUPPORTED_KEYS = [
        'dataset_id',
        'datasetId',
        'datasetID',
    ];

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

        $authorizedDatasetIds = $this->retrieveAuthorizedDatasets();

        if (!$authorizedDatasetIds->contains($requestedDatasetId)) {
            abort(403, 'Unauthorized dataset');
        }

        return $next($request);
    }

    /**
     * Retrieve the authorized dataset IDs of the user. The underlying repository takes care of caching.
     *
     * @return Collection
     */
    protected function retrieveAuthorizedDatasets(): Collection
    {
        try {
            return Datasets::getUserDatasetIds();
        } catch (RequestException $e) {
            Log::error('Unable to request authorized datasets from user tool:', $e->getTrace());
            abort(401, 'Unable to authorize dataset access.');
        }
    }

    /**
     * (Try to) find a dataset ID within the given request. If multiple distinct ones are found, the request is aborted.
     *
     * @param Request $request
     * @return null|string Dataset ID, if exactly one unique one was found. Null if no dataset IDs could be found.
     */
    protected function getRequestedDatasetId(Request $request): ?string
    {
        // Collection to store all potential dataset IDs that occur in the request.
        $potentialDatasetIds = collect();

        // Find all potential dataset IDs.
        foreach (static::SUPPORTED_KEYS as $supportedKey) {
            $potentialDatasetIds->push($request->input($supportedKey));
            $potentialDatasetIds->push($request->route($supportedKey));
        }

        // Reduce to only the unique non-null dataset IDs.
        $notNull = $potentialDatasetIds->whereNotNull()->unique();

        // There is no good reason to specify multiple different dataset IDs in the request and since we don't know
        // which dataset ID is going to be used by the application, this can lead to serious exploits.
        if ($notNull->count() > 1) {
            abort(401, 'Multiple dataset IDs found in the request.');
        }

        // Return the dataset ID if there is one unique one. If the collection is empty, null is returned.
        return $notNull->first();
    }
}
