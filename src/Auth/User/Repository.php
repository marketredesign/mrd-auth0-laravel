<?php

namespace Marketredesign\MrdAuth0Laravel\Auth\User;

use Illuminate\Contracts\Auth\Authenticatable;
use Marketredesign\MrdAuth0Laravel\Model\Stateful\User as StatefulUser;
use Marketredesign\MrdAuth0Laravel\Model\Stateless\User as StatelessUser;

class Repository
{
    public function fromAccessToken(array $decodedJwt): Authenticatable
    {
        return new StatelessUser($decodedJwt);
    }
}
