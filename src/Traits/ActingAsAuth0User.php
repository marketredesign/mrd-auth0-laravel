<?php

declare(strict_types=1);

namespace Marketredesign\MrdAuth0Laravel\Traits;

use Auth0\Laravel\Traits\ActingAsAuth0User as BaseTrait;

/**
 * Should be removed once provided by the underlying auth0/login package.
 * @deprecated
 */
trait ActingAsAuth0User
{
    use BaseTrait;
}
