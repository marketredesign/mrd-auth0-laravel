<?php

namespace Marketredesign\MrdAuth0Laravel\Auth;

use Facile\JoseVerifier\AbstractTokenVerifier;
use Facile\JoseVerifier\AbstractTokenVerifierBuilder;

class JoseBuilder extends AbstractTokenVerifierBuilder
{
    protected ?string $expectedAudience;

    public function __construct(?string $expectedAudience)
    {
        $this->expectedAudience = $expectedAudience;
    }

    /**
     * @inheritDoc
     */
    protected function getVerifier(string $issuer, string $clientId): AbstractTokenVerifier
    {
        return new JwtVerifier($issuer, $this->expectedAudience ?? $clientId, $this->buildDecrypter());
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
