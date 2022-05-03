<?php


namespace Marketredesign\MrdAuth0Laravel\Repository;

use Auth0\Laravel\Auth0;
use Auth0\SDK\Contract\API\ManagementInterface;
use Auth0\SDK\Utility\HttpResponse;
use Auth0\SDK\Utility\Request\FilteredRequest;
use Auth0\SDK\Utility\Request\RequestOptions;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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
     * UserRepository constructor.
     * @param Auth0 $auth0
     */
    public function __construct(Auth0 $auth0)
    {
        $this->mgmtApi = $auth0->getSdk()->management();
        $this->cacheTTL = config('mrd-auth0.cache_ttl');
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

            if (HttpResponse::wasSuccessful($response)) {
                return json_decode($response->getBody());
            } elseif (HttpResponse::getStatusCode($response) == 404) {
                return null;
            } else {
                throw new HttpException(HttpResponse::getStatusCode($response), HttpResponse::getContent($response));
            }
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
        if ($response->getStatusCode() != 204) {
            throw new HttpException($response->getStatusCode());
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

        if ($response->getStatusCode() != 201) {
            throw new HttpException($response->getStatusCode());
        }

        // body of response should be the newly created user object
        return json_decode($response->getBody());
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

        // Create Lucene query on user IDs.
        $query = $queryField . ':("' . implode('" OR "', $queryValues->unique()->all()) . '")';
        // Create request options including the requested fields.
        $options = new RequestOptions(new FilteredRequest($fields, true));
        // Create cache key based on the query and fields.
        $cacheKey = 'auth0-users-all-' . hash('sha256', $query . implode(',', $fields ?? []));

        // Send request to the Auth0 Management API and cache the result.
        return Cache::remember($cacheKey, $this->cacheTTL, function () use ($query, $options, $queryField) {
            $response = $this->mgmtApi->users()->getAll(['q' => $query], $options);
            $users = json_decode($response->getBody());

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
     * @inheritdoc
     */
    public function getAllUsers(): Collection
    {
        $response = $this->mgmtApi->users()->getAll();
        if ($response->getStatusCode() != 200) {
            throw new HttpException($response->getStatusCode());
        }

        $users = json_decode($response->getBody());

        return collect($users)->keyBy('user_id');
    }

    /**
     * @inheritDoc
     */
    public function getByEmails(Collection $emails, array $fields = null): Collection
    {
        return $this->getAll('email', $emails, $fields);
    }
}
