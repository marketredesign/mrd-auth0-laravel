<?php

namespace Marketredesign\MrdAuth0Laravel\Contracts;

use Exception;

interface Auth0Repository
{
    /**
     * Get a machine-to-machine token for this service. The access token is retrieved from cache when less than half
     * of its expiration time has passed. Otherwise, a new one is retrieved.
     *
     * @return string Machine-to-machine token
     * @throws Exception If not running in console (e.g. not from async job).
     */
    public function getMachineToMachineToken(): string;
}
