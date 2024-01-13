<?php

declare(strict_types=1);

namespace Marketredesign\MrdAuth0Laravel\Traits;

use Illuminate\Contracts\Auth\Authenticatable;
use Marketredesign\MrdAuth0Laravel\Model\Stateless\User as StatefulUser;
use Marketredesign\MrdAuth0Laravel\Model\Stateless\User as StatelessUser;

trait ActingAsPricecypherUser
{
    abstract public function actingAs(Authenticatable $user, $guard = null);

    /**
     * use this method to impersonate a specific auth0 user.
     * if you pass an attributes array, it will be merged with a set of default values
     *
     * @param array $attributes
     * @param bool $stateless
     * @return mixed
     */
    public function actingAsPricecypherUser(array $attributes = [], $stateless = true)
    {
        $defaults = [
            'sub' => 'some-auth0-user-id',
            'azp' => 'some-auth0-appplication-client-id',
            'iat' => time(),
            'exp' => time() + 60 * 60,
            'scope' => '',
        ];

        if ($stateless) {
            $user = new StatelessUser(array_merge($defaults, $attributes));
        } else {
            // TODO use actual stateful user instead of stateless.
            $user = new StatefulUser(array_merge($defaults, $attributes));
        }

        return $this->actingAs($user, 'jwt');
    }
}
