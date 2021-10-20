<?php


namespace Marketredesign\MrdAuth0Laravel\Contracts;

use Illuminate\Support\Collection;

interface UserRepository
{
    /**
     * Retrieve a single user object with the given ID.
     *
     * @param mixed $id The ID of the user to retrieve.
     * @return null|object The retrieved user, or null if it does not exist.
     */
    public function get($id): ?object;

    /**
     * Retrieve a collection of users with the given IDs, optionally limited to only contain the given fields.
     * The returned collection is keyed by the user IDs.
     *
     * @param Collection $ids User IDs to retrieve user info for.
     * @param array|null $fields Fields to be retrieved for each user.
     * @return Collection Keyed by user ID, containing an object for each user.
     */
    public function getByIds(Collection $ids, array $fields = null): Collection;

    /**
     * Retrieve all users within Auth0, optionally limited to only contain the given fields.
     * The returned collection is keyed by the user IDs.
     *
     * @return Collection Keyed by user ID, containing an object for each user.
     */
    public function getAllUsers(): Collection;

    /**
     * Retrieve a collection of users with the given emails, optionally limited to only contain the given fields.
     * The returned collection is keyed by the email addresses.
     *
     * @param Collection $emails Email addresses to retrieve user info for.
     * @param array|null $fields Fields to be retrieved for each user.
     * @return Collection Keyed by email, containing an object for each user.
     */
    public function getByEmails(Collection $emails, array $fields = null): Collection;

    /**
     * Delete the user with given userID from the Auth0 database
     *
     * @param mixed $id
     */
    public function delete($id);
}
