<?php

namespace Marketredesign\MrdAuth0Laravel\Auth;

use Facile\JoseVerifier\AbstractTokenVerifier;
use Facile\JoseVerifier\AbstractTokenVerifierBuilder;

class JoseBuilder extends AbstractTokenVerifierBuilder
{
    /**
     * @inheritDoc
     */
    protected function getVerifier(string $issuer, string $clientId): AbstractTokenVerifier
    {
        return new JwtVerifier($issuer, $clientId, $this->buildDecrypter());
    }

    protected function getExpectedAlg(): ?string
    {
        return null;
    }

    protected function getExpectedEncAlg(): ?string
    {
        return null;
    }

    protected function getExpectedEnc(): ?string
    {
        return null;
    }
}
