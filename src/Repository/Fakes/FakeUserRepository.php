<?php


namespace Marketredesign\MrdAuth0Laravel\Repository\Fakes;

use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Marketredesign\MrdAuth0Laravel\Contracts\UserRepository;

class FakeUserRepository implements UserRepository
{
    use WithFaker;

    private $userIds;
    private $userObjects;
    private Collection $userRoles;

    public function __construct()
    {
        $this->fakeClear();
    }

    /**
     * @return int Count number of users in fake user repository.
     */
    public function fakeCount(): int
    {
        return $this->userIds->count();
    }

    /**
     * Clear fake user repository.
     */
    public function fakeClear(): void
    {
        $this->userIds = collect();
        $this->userObjects = collect();
        $this->userRoles = collect();
        $this->setUpFaker();
    }

    /**
     * Add collection of user IDs for which a random user object will be returned when the repository is queried.
     *
     * @param Collection $ids User IDs to create user objects for.
     */
    public function fakeAddUsers(Collection $ids): void
    {
        $this->userIds = $this->userIds->concat($ids);
    }

    /**
     * Gets a random user object for the given ID, which will be created if it does not already exist.
     *
     * @param $id mixed User ID to get the object for.
     * @return mixed|object
     */
    private function getRandomUserObjectForId($id)
    {
        if ($this->userObjects->has($id)) {
            return $this->userObjects->get($id);
        }

        $firstName = $this->faker->firstName;
        $lastName = $this->faker->lastName;

        $user = (object)[
            "created_at" => $this->faker->dateTime,
            "email"=> $this->faker->unique()->email,
            "email_verified" => $this->faker->boolean,
            "family_name" => $lastName,
            "given_name" => $firstName,
            "id" => $this->faker->randomNumber(),
            "locale" => $this->faker->locale,
            "name" => $firstName . ' ' . $lastName,
            "nickname" => $this->faker->name,
            "user_id" => $id,
            "last_ip" => $this->faker->ipv4,
            "last_login" => $this->faker->dateTime,
            "logins_count" => $this->faker->randomNumber(),
        ];

        $this->userObjects->put($id, $user);

        return $user;
    }

    /**
     * @inheritDoc
     */
    public function get($id): ?object
    {
        if (!$this->userIds->contains($id)) {
            return null;
        }

        return $this->getRandomUserObjectForId($id);
    }

    /**
     * @inheritDoc
     */
    public function delete($id)
    {
        $this->userIds = $this->userIds->filter(function ($userID) use ($id) {
            return $userID != $id;
        });

        $this->userObjects->forget($id);
    }

    /**
     * @inheritDoc
     */
    public function getByIds(Collection $ids, array $fields = null): Collection
    {
        return $ids->mapWithKeys(function ($id) {
            if (!$this->userIds->contains($id)) {
                return [];
            }

            return [$id => $this->getRandomUserObjectForId($id)];
        });
    }

    /**
     * @inheritdoc
     */
    public function getAllUsers(): Collection
    {
        return $this->userIds->mapWithKeys(function ($id) {
            return [$id => $this->getRandomUserObjectForId($id)];
        });
    }

    /**
     * @inheritDoc
     */
    public function getByEmails(Collection $emails, array $fields = null): Collection
    {
        return $this->userObjects->filter(function (Object $user) use ($emails) {
            return $emails->contains($user->email);
        });
    }

    /**
     * @inheritDoc
     */
    public function createUser(String $email, String $firstName, String $lastName): object
    {
        $userId = Str::random(20);
        $this->fakeAddUsers(collect($userId));

        $userModel = $this->get($userId);
        $userModel->email = $email;
        $userModel->family_name = $lastName;
        $userModel->given_name = $firstName;
        $userModel->name = $firstName . ' ' . $lastName;

        return $userModel;
    }

    /**
     * @inheritDoc
     */
    public function getRoles(string $userId): Collection
    {
        return $this->userRoles->get($userId, collect());
    }

    /**
     * @inheritDoc
     */
    public function addRoles(string $userId, Collection $roleIds): void
    {
        $roles = $this->getRoles($userId);

        $roles->push(...$roleIds);

        $this->userRoles->put($userId, $roles->unique());
    }

    /**
     * @inheritDoc
     */
    public function removeRoles(string $userId, Collection $roleIds): void
    {
        $roles = $this->getRoles($userId);

        $this->userRoles->put($userId, $roles->diff($roleIds));
    }
}
