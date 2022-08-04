<?php

namespace Marketredesign\MrdAuth0Laravel\Repository\Fakes;

use Marketredesign\MrdAuth0Laravel\Repository\Auth0Repository;

class FakeAuth0Repository extends Auth0Repository
{
    private int $m2mExpiresIn = 86400;
    private string $m2mAccessToken = 'mocked_access_token';

    /**
     * Set the fake expires in attribute of the fake machine-to-machine token.
     *
     * @param int $expiresIn Time in seconds when the fake m2m token "expires".
     * @return void
     */
    public function fakeSetM2mExpiresIn(int $expiresIn): void
    {
        $this->m2mExpiresIn = $expiresIn;
    }

    /**
     * Set the fake access token attribute of the fake machine-to-machine token.
     *
     * @param string $accessToken Fake machine-to-machine token.
     * @return void
     */
    public function fakeSetM2mAccessToken(string $accessToken): void
    {
        $this->m2mAccessToken = $accessToken;
    }

    /**
     * @inheritDoc
     */
    protected function retrieveDecodedM2mTokenResponse(): array
    {
        return [
            'expires_in' => $this->m2mExpiresIn,
            'access_token' => $this->m2mAccessToken,
        ];
    }
}
