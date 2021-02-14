<?php


namespace Marketredesign\MrdAuth0Laravel\Repository;

use Auth0\SDK\API\Management;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class UserRepository implements \Marketredesign\MrdAuth0Laravel\Contracts\UserRepository
{
    private const CACHE_TTL = 1800;

    private $mgmtApi;

    /**
     * UserRepository constructor.
     * @param Management $management
     */
    public function __construct(Management $management)
    {
        $this->mgmtApi = $management;
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

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($id) {
            try {
                // TODO handle API rate limit. The limit is high enough that we should not get in trouble anytime soon.
                return json_decode($this->mgmtApi->users()->get($id)->getBody());
            } catch (RequestException $e) {
                if ($e->getCode() == 404) {
                    return null;
                } else {
                    throw $e;
                }
            }
        });
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
        // Create comma separated string for fields.
        $fields = $fields === null ? null : implode(',', $fields);
        // Create cache key based on the query and fields.
        $cacheKey = 'auth0-users-all-' . hash('sha256', $query . $fields);

        // Send request to the Auth0 Management API and cache the result.
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($query, $fields, $queryField) {
            // TODO handle the API rate limit. The limit is high enough that we should not get in trouble anytime soon.
            $response = $this->mgmtApi->users()->getAll(['q' => $query], $fields);
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
     * @inheritDoc
     */
    public function getByEmails(Collection $emails, array $fields = null): Collection
    {
        return $this->getAll('email', $emails, $fields);
    }
}
