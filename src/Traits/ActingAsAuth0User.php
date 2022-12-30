<?php

declare(strict_types=1);

namespace Marketredesign\MrdAuth0Laravel\Traits;

use Auth0\Laravel\Model\Stateful\User as StatefulUser;
use Auth0\Laravel\Model\Stateless\User as StatelessUser;
use Auth0\Laravel\StateInstance;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Should be removed once provided by the underlying auth0/login package.
 * @see https://github.com/auth0/laravel-auth0/blob/main/src/Traits/ActingAsAuth0User.php
 */
trait ActingAsAuth0User
{
    abstract public function actingAs(Authenticatable $user, $guard = null);

    /**
     * use this method to impersonate a specific auth0 user.
     * if you pass an attributes array, it will be merged with a set of default values
     *
     * @param array $attributes
     *
     * @return mixed
     */
    public function actingAsAuth0User(array $attributes = [], $stateless = true)
    {
        $defaults = [
            'sub' => 'some-auth0-user-id',
            'azp' => 'some-auth0-appplication-client-id',
            'iat' => time(),
            'exp' => time() + 60 * 60,
            'scope' => '',
        ];

        if ($stateless) {
            $auth0user = new StatelessUser(array_merge($defaults, $attributes));
        } else {
            $auth0user = new StatefulUser(array_merge($defaults, $attributes));
        }

        if ($auth0user->getAttribute('scope')) {
            app()->make(StateInstance::class)->setAccessTokenScope(explode(' ', $auth0user->getAttribute('scope')));
        }

        return $this->actingAs($auth0user, 'auth0');
    }
}
