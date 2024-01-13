<?php

namespace Marketredesign\MrdAuth0Laravel\Auth\User;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;

class Provider implements UserProvider
{
    /**
     * @inheritDoc
     */
    public function retrieveById($identifier)
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function retrieveByToken($identifier, $token)
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function updateRememberToken(Authenticatable $user, $token)
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function retrieveByCredentials(array $credentials)
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        return null;
    }

    public function getRepository(): Repository
    {
        static $repository = null;

        if ($repository === null) {
            $repository = new Repository();
        }

        return $repository;
    }
}
