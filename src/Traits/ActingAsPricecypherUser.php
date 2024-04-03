<?php

namespace Marketredesign\MrdAuth0Laravel\Traits;

use Marketredesign\MrdAuth0Laravel\Model\Stateful\User as StatefulUser;
use Marketredesign\MrdAuth0Laravel\Model\Stateless\User as StatelessUser;

trait ActingAsPricecypherUser
{
    public array $defaultActingAsAttributes = [
        'sub' => 'test-user-id',
        'scope' => '',
    ];

    public function actingAsPricecypherUser(array $attributes = [], $stateless = true): self
    {
        $issued = time();
        $expires = $issued + 3600;
        $attributes = array_merge($this->defaultActingAsAttributes, ['iat' => $issued, 'exp' => $expires], $attributes);
        $guard = $stateless ? 'pc-jwt' : 'pc-oidc';

        if ($stateless) {
            $user = new StatelessUser($attributes);
        } else {
            $user = new StatefulUser($attributes);
        }

        return $this->actingAs($user, $guard);
    }
}
