<?php


namespace Marketredesign\MrdAuth0Laravel\Repository;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Marketredesign\MrdAuth0Laravel\Http\Resources\DatasetResource;

class DatasetRepository implements \Marketredesign\MrdAuth0Laravel\Contracts\DatasetRepository
{
    protected $guzzleOptions;

    /**
     * DatasetRepository constructor.
     */
    public function __construct()
    {
        $this->guzzleOptions = config('mrd-auth0.guzzle_options');
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
     * @inheritDoc
     */
    public function getUserDatasetIds(): Collection
    {
        $datasets = collect($this->get('/datasets')->get('datasets'));

        return $datasets->pluck('id');
    }

    /**
     * @inheritDoc
     */
    public function getUserDatasets(): ResourceCollection
    {
        $datasets = $this->get('/datasets')->get('datasets');

        return DatasetResource::collection($datasets);
    }
}
