<?php


namespace Marketredesign\MrdAuth0Laravel\Repository;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Marketredesign\MrdAuth0Laravel\Http\Resources\DatasetResource;

class DatasetRepository implements \Marketredesign\MrdAuth0Laravel\Contracts\DatasetRepository
{
    /**
     * @var int Time to store user allowed datasets in cache, in seconds.
     */
    protected $cacheTTL;

    protected $guzzleOptions;

    /**
     * DatasetRepository constructor.
     */
    public function __construct()
    {
        $this->guzzleOptions = config('mrd-auth0.guzzle_options');
        $this->cacheTTL = config('mrd-auth0.cache_ttl');
    }

    /**
     * Adds Authorization and Accept headers to the given Guzzle options, if they are not already defined.
     *
     * @param array $options Associative array of guzzle options. Defaults to empty array.
     * @return array
     */
    protected function addDefaultsToGuzzleOptions(array $options = []): array
    {
        $token = Request::bearerToken();

        return array_merge(['headers' => [
            'Authorization' => "Bearer $token",
            'Accept' => 'application/json'
        ]], $options, $this->guzzleOptions);
    }

    /**
     * Send GET request to User Tool API.
     *
     * @param string $uri URI of user tool API to send request to.
     * @param array $options extra options to send in the request, like query parameters. Defaults to empty array.
     * @return Collection json decoded response as Collection.
     * @throws RequestException
     */
    protected function get(string $uri, array $options = []): Collection
    {
        $baseUrl = config('mrd-auth0.user_tool_url');

        try {
            $http = new Client;
            $options = $this->addDefaultsToGuzzleOptions($options);
            $jsonResponse = $http->get($baseUrl . $uri, $options);

            return collect(json_decode((string) $jsonResponse->getBody(), false));
        } catch (GuzzleException $e) {
            if ($e instanceof RequestException) {
                // Let the caller handle this error.
                throw $e;
            } else {
                // This kind of exception in not expected and probably indicates some connection problem. In any case,
                // we cannot recover from it. Hence, log the error and abort with an internal server error.
                Log::error('Encountered an unexpected GuzzleException:');
                Log::error($e);
                abort(500);
            }
        }
    }

    /**
     * Retrieve user datasets by calling the API of user tool directly, without caching.
     *
     * @param bool $managedOnly Only retrieve datasets that the user is a manager of.
     * @return array
     * @throws RequestException
     */
    private function retrieveDatasetsFromApi(bool $managedOnly): array
    {
        return $this->get('/datasets', ['query' => ['managed_only' => $managedOnly]])->get('datasets');
    }

    /**
     * Get user datasets from cache, when enabled and present, or by retrieving from the API otherwise.
     *
     * @param bool $managedOnly Only get datasets that the user is a manager of.
     * @param bool $cached Use {@code false} to disable retrieving from and storing in cache. Defaults to {@code true}.
     * @return array
     */
    private function getRawDatasets(bool $managedOnly, bool $cached): array
    {
        if (!$cached) {
            return $this->retrieveDatasetsFromApi($managedOnly);
        }

        $userId = Auth::id();

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
    public function getUserDatasetIds(bool $managedOnly = false, bool $cached = true): Collection
    {
        return collect($this->getRawDatasets($managedOnly, $cached))->pluck('id');
    }

    /**
     * @inheritDoc
     */
    public function getUserDatasets(bool $managedOnly = false, bool $cached = true): ResourceCollection
    {
        return DatasetResource::collection($this->getRawDatasets($managedOnly, $cached));
    }
}
