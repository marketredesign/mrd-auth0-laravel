<?php

namespace Marketredesign\MrdAuth0Laravel\Auth;

use Facile\JoseVerifier\AbstractTokenVerifier;
use Facile\JoseVerifier\Checker\AuthTimeChecker;
use Facile\JoseVerifier\Checker\AzpChecker;
use Facile\JoseVerifier\Checker\NonceChecker;
use Facile\JoseVerifier\Decrypter\TokenDecrypterInterface;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Jose\Component\Checker\AudienceChecker;
use Jose\Component\Checker\ClaimChecker;
use Jose\Component\Checker\ClaimCheckerManager;
use Jose\Component\Checker\ExpirationTimeChecker;
use Jose\Component\Checker\IssuedAtChecker;
use Jose\Component\Checker\IssuerChecker;
use Jose\Component\Checker\NotBeforeChecker;

final class JwtVerifier extends AbstractTokenVerifier
{
    private string $audience;

    public function __construct(string $issuer, string $audience, ?TokenDecrypterInterface $decrypter = null)
    {
        parent::__construct($issuer, 'not used', $decrypter);
        $this->audience = $audience;
    }

    public function verify(string $jwt): array
    {
        $jwks = JWK::parseKeySet($this->jwksProvider->getJwks());
        $claims = (array)JWT::decode($jwt, $jwks);
        $claimChecker = new ClaimCheckerManager($this->getClaimCheckers());

        $claimChecker->check($claims, ['iss', 'sub', 'aud', 'exp', 'iat']);

        return $claims;
    }

    /**
     * @return ClaimChecker[]
     */
    protected function getClaimCheckers(): array
    {
        $checkers = [
            new IssuerChecker([$this->issuer], true),
            new IssuedAtChecker($this->clockTolerance, true, $this->clock),
            new AudienceChecker($this->audience, true),
            new ExpirationTimeChecker($this->clockTolerance, false, $this->clock),
            new NotBeforeChecker($this->clockTolerance, true, $this->clock),
        ];

        if (null !== $this->azp) {
            $checkers[] = new AzpChecker($this->azp);
        }

        if (null !== $this->nonce) {
            $checkers[] = new NonceChecker($this->nonce);
        }

        if (null !== $this->maxAge) {
            $checkers[] = new AuthTimeChecker($this->maxAge, $this->clockTolerance);
        }

        return $checkers;
    }
}
