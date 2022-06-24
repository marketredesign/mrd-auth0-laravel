<?php


namespace Marketredesign\MrdAuth0Laravel\Repository;

use Auth0\Laravel\Auth0;
use Auth0\SDK\Contract\API\ManagementInterface;
use Auth0\SDK\Utility\HttpResponse;
use Auth0\SDK\Utility\Request\FilteredRequest;
use Auth0\SDK\Utility\Request\RequestOptions;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;

class UserRepository implements \Marketredesign\MrdAuth0Laravel\Contracts\UserRepository
{
    /**
     * @var ManagementInterface
     */
    protected $mgmtApi;

    /**
     * @var int Time to live for cache entries stored by this repository, in seconds.
     */
    protected $cacheTTL;

    /**
     * @var int User chunk size.
     */
    protected $chunkSize;

    /**
     * UserRepository constructor.
     * @param Auth0 $auth0
     */
    public function __construct(Auth0 $auth0)
    {
        $this->mgmtApi = $auth0->getSdk()->management();
        $this->cacheTTL = config('mrd-auth0.cache_ttl', 300);
        $this->chunkSize = config('mrd-auth0.chunk_size', 50);
    }

    /**
     * Extract the content from an HTTP response as returned by the management API and parse as JSON.
     * An HTTP exception is thrown in case the request was not successful to begin with.
     * An internal server error is generated when the JSON response could not be parsed.
     *
     * @param ResponseInterface $response
     * @param int $expStatusCode The expected HTTP status code for the response to be considered successful.
     * @param bool $associative When {@code true}, returned objects will be converted into associative arrays.
     * @return mixed|void
     */
    private function decodeResponse(ResponseInterface $response, int $expStatusCode = 200, bool $associative = false)
    {
        // Throw exception if request was not successful.
        if (!HttpResponse::wasSuccessful($response, $expStatusCode)) {
            throw new HttpException($response->getStatusCode(), HttpResponse::getContent($response));
        }

        try {
            // Return decoded response.
            return json_decode(HttpResponse::getContent($response), $associative, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            Log::error('An error occurred while decoding Auth0 management response.', $e->getTrace());
            abort(500, 'Error while decoding Auth0 management response.');
        }
    }

    /**
     * @inheritDoc
     */
    public function get($id): ?object
    {
        if ($id == null) {
            return null;
        }

        $cacheKey = 'auth0-users-get-' . $id;

        return Cache::remember($cacheKey, $this->cacheTTL, function () use ($id) {
            $response = $this->mgmtApi->users()->get($id);

            if (HttpResponse::getStatusCode($response) == 404) {
                return null;
            }

            return $this->decodeResponse($response, 200, false);
        });
    }

    /**
     * @inheritDoc
     */
    public function delete($id)
    {
        if ($id == null) {
            return null;
        }

        $response = $this->mgmtApi->users()->delete($id);

        if (!HttpResponse::wasSuccessful($response, 204)) {
            throw new HttpException(HttpResponse::getStatusCode($response), HttpResponse::getContent($response));
        }
    }

    /**
     * @inheritdoc
     */
    public function createUser(String $email, String $firstName, String $lastName): object
    {
        $response = $this->mgmtApi->users()->create(config('mrd-auth0.connection'), [
            'email' => $email,
            'given_name' => $firstName,
            'family_name' => $lastName,
            'name' => $firstName . ' ' . $lastName,
            // hash a random 16 character string to generate initial random password
            'password' => Hash::make(Str::random())
        ]);

        // body of response should be the newly created user object
        return $this->decodeResponse($response, 201);
    }

    /**
     * Retrieves a collection of users from the Auth0 management API, queried on the given queryField looking for users
     * with the given queryValues. The result is optionally limited to only contain the given fields. The returned
     * collection will be keyed by the queryField.
     *
     * @param string $queryField Field on which the users are queried.
     * @param Collection $queryValues The values of queryField used to query the users.
     * @param array|null $fields Fields to be retrieved for each user.
     * @return Collection Keyed by queryField, containing an object for each user.
     */
    private function getAll(string $queryField, Collection $queryValues, array $fields = null): Collection
    {
        // Make sure the queryField is always contained in the response, such that we can key the result on that.
        if ($fields !== null && !Arr::has($fields, $queryField)) {
            $fields[] = $queryField;
        }

        // Find the unique query values.
        $uniqueQueryValues = $queryValues->unique();

        // Create request options including the requested fields.
        $options = new RequestOptions(new FilteredRequest($fields, true));

        // Create cache key based on the query and fields.
        $qValsString = implode(',', $uniqueQueryValues->all());
        $fieldsString = implode(',', $fields ?? []);
        $cacheKey = 'auth0-users-all-' . hash('sha256', "$queryField:$qValsString;$fieldsString");

        // Send request(s) to the Auth0 Management API and cache the result.
        return Cache::remember($cacheKey, $this->cacheTTL, function () use ($options, $queryField, $uniqueQueryValues) {
            $users = collect();

            // Chunk query values
            foreach ($uniqueQueryValues->chunk($this->chunkSize) as $qValsChunk) {
                // Create Lucene query on user IDs.
                $query = $queryField . ':("' . implode('" OR "', $qValsChunk->all()) . '")';
                // Send request to Auth0 Management API.
                $response = $this->decodeResponse($this->mgmtApi->users()->getAll(['q' => $query], $options));
                // Find users in the response and add to collection.
                $users = $users->concat($response);
            }

            return collect($users)->keyBy($queryField);
        });
    }

    /**
     * @inheritDoc
     */
    public function getByIds(Collection $ids, array $fields = null): Collection
    {
        return $this->getAll('user_id', $ids, $fields);
    }

    /**
     * @inheritDoc
     */
    public function getByEmails(Collection $emails, array $fields = null): Collection
    {
        return $this->getAll('email', $emails, $fields);
    }

    /**
     * Calls the management API using {@code $callApi} as often as required and combines the results of the different
     * pages into one collection.
     *
     * @param string $responseKey The key in the response from the management API containing the desired data.
     * @param Closure $callApi Function that makes the actual calls to the management API. It must accept a param array
     *  that is passed to the calls to the management API (containing the requested page etc).
     * @return Collection
     */
    private function getFromMgmtPaginated(string $responseKey, Closure $callApi)
    {
        // Keep track of current page.
        $page = 0;
        // Function to create management API parameters including the requested page.
        $createParams = fn($page) => ['include_totals' => true, 'page' => $page];
        // Perform initial call to management API and decode the response.
        $initialResponse = $this->decodeResponse($callApi($createParams($page)));
        // Collect the desired results from the response.
        $result = collect($initialResponse->$responseKey);
        // Find page metadata in the response.
        $total = $initialResponse->total;
        $start = $initialResponse->start;
        $length = $initialResponse->length;

        // We keep calling the management API until there is no data left.
        while ($start + $length < $total) {
            $page++;
            // Get new response for the next page.
            $response = $this->decodeResponse($callApi($createParams($page)));
            // Add the results of the new response to the collection.
            $result = $result->concat($response->$responseKey);
            // Update start and length values such that we know when we are finished.
            $start = $response->start;
            $length = $initialResponse->length;
        }

        // Finally, return the collection with the combined results.
        return $result;
    }

    /**
     * @inheritdoc
     */
    public function getAllUsers(): Collection
    {
        return Cache::remember('auth0-all-users', $this->cacheTTL, function () {
            $users = $this->getFromMgmtPaginated('users', fn($params) => $this->mgmtApi->users()->getAll($params));

            return $users->keyBy('user_id');
        });
    }
}
