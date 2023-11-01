<?php


namespace Marketredesign\MrdAuth0Laravel\Repository;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Marketredesign\MrdAuth0Laravel\Exceptions\NotImplementedException;

class UserRepository implements \Marketredesign\MrdAuth0Laravel\Contracts\UserRepository
{
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
     */
    public function __construct()
    {
        $this->cacheTTL = config('mrd-auth0.cache_ttl', 300);
        $this->chunkSize = config('mrd-auth0.chunk_size', 50);
    }

    private function warnAuth0(): void
    {
        Log::warning('[mrd-auth0-laravel] User Repository no longer supports Auth0 SDK directly. WIP');
    }

    /**
     * @inheritDoc
     */
    public function get($id): ?object
    {
        if ($id == null) {
            return null;
        }

        $this->warnAuth0();

        return (object)[
            'sub' => $id,
            'user_id' => $id,
            'email' => $id,
        ];
    }

    /**
     * @inheritDoc
     */
    public function delete($id)
    {
        if ($id == null) {
            return null;
        }

        throw new NotImplementedException('Identity provider not (yet) supported.');
    }

    /**
     * @inheritdoc
     */
    public function createUser(String $email, String $firstName, String $lastName): object
    {
        throw new NotImplementedException('Identity provider not (yet) supported.');
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
        throw new NotImplementedException('Identity provider not (yet) supported.');
    }

    /**
     * @inheritDoc
     */
    public function getByIds(Collection $ids, array $fields = null): Collection
    {
        $this->warnAuth0();

        return $ids->map(function ($id) {
            return (object)[
                'sub' => $id,
                'user_id' => $id,
                'email' => $id,
            ];
        });
    }

    /**
     * @inheritDoc
     */
    public function getByEmails(Collection $emails, array $fields = null): Collection
    {
        return $this->getAll('email', $emails, $fields);
    }

    /**
     * @inheritdoc
     */
    public function getAllUsers(): Collection
    {
        throw new NotImplementedException('Identity provider not (yet) supported.');
    }

    /**
     * @inheritdoc
     */
    public function getRoles(string $userId): Collection
    {
        throw new NotImplementedException('Identity provider not (yet) supported.');
    }

    /**
     * @inheritdoc
     */
    public function addRoles(string $userId, Collection $roleIds): void
    {
        throw new NotImplementedException('Identity provider not (yet) supported.');
    }

    /**
     * @inheritdoc
     */
    public function removeRoles(string $userId, Collection $roleIds): void
    {
        throw new NotImplementedException('Identity provider not (yet) supported.');
    }
}
