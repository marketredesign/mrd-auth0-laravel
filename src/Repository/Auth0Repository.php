<?php

namespace Marketredesign\MrdAuth0Laravel\Repository;

use Auth0\Laravel\Facade\Auth0;
use Exception;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;

class Auth0Repository implements \Marketredesign\MrdAuth0Laravel\Contracts\Auth0Repository
{
    /**
     * Retrieve the machine-to-machine token (from underlying SDK).
     *
     * @return array Decoded response, containing 'expires_in' and 'access_token' attributes.
     */
    protected function retrieveDecodedM2mTokenResponse(): array
    {
        $clientCredResponse = Auth0::getSdk()->authentication()->clientCredentials()->getBody()->getContents();

        return json_decode($clientCredResponse, true);
    }

    /**
     * @inheritDoc
     */
    public function getMachineToMachineToken(): string
    {
        // Ensure function was called while running in console (e.g. an async job).
        if (!App::runningInConsole()) {
            throw new Exception('Machine to machine tokens are only supposed to be used in CLI runs.');
        }

        // Store m2m response from either of the callbacks (to retrieve TTL / access token), such that we only have to
        // make 1 request.
        $m2mResp = null;

        return Cache::remember(
            'auth0-m2m-token',
            function () use (&$m2mResp) {
                $m2mResp ??= $this->retrieveDecodedM2mTokenResponse();
                return (int) ($m2mResp['expires_in'] / 2);
            },
            function () use (&$m2mResp) {
                $m2mResp ??= $this->retrieveDecodedM2mTokenResponse();
                return $m2mResp['access_token'];
            }
        );
    }
}
