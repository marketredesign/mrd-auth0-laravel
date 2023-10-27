<?php

namespace Marketredesign\MrdAuth0Laravel\Auth;

use Facile\JoseVerifier\AbstractTokenVerifier;
use Facile\JoseVerifier\Checker\AuthTimeChecker;
use Facile\JoseVerifier\Checker\AzpChecker;
use Facile\JoseVerifier\Checker\NonceChecker;
use Facile\JoseVerifier\Validate\Validate;
use Jose\Component\Checker\AlgorithmChecker;
use Jose\Component\Checker\AudienceChecker;
use Jose\Component\Checker\ExpirationTimeChecker;
use Jose\Component\Checker\IssuedAtChecker;
use Jose\Component\Checker\IssuerChecker;
use Jose\Component\Checker\NotBeforeChecker;
use Throwable;

final class JwtVerifier extends AbstractTokenVerifier
{
    public function verify(string $jwt): array
    {
        $jwt = $this->decrypt($jwt);
        $validator = $this->create($jwt)->mandatory(['iss', 'sub', 'exp', 'iat', 'aud']);

        try {
            return $validator->run();
        } catch (Throwable $e) {
            throw $this->processException($e);
        }
    }

    protected function create(string $jwt): Validate
    {
        $mandatoryClaims = [];

        $expectedIssuer = $this->issuer;

        if ($this->aadIssValidation) {
            $payload = $this->getPayload($jwt);
            $expectedIssuer = str_replace('{tenantid}', (string) ($payload['tid'] ?? ''), $expectedIssuer);
        }

        $buildJwks = function($jwt) {
            return $this->buildJwks($jwt);
        };

        $validator = Validate::token($jwt)
            ->keyset($buildJwks->call($this, $jwt))
            ->claim(new IssuerChecker([$expectedIssuer], true))
            ->claim(new IssuedAtChecker($this->clockTolerance, true, $this->clock))
            ->claim(new AudienceChecker($this->clientId, true))
            ->claim(new ExpirationTimeChecker($this->clockTolerance, false, $this->clock))
            ->claim(new NotBeforeChecker($this->clockTolerance, true, $this->clock));

        dd('passed');

        if (null !== $this->azp) {
            $validator = $validator->claim(new AzpChecker($this->azp));
        }

        if (null !== $this->expectedAlg) {
            $validator = $validator->header(new AlgorithmChecker([$this->expectedAlg], true));
        }

        if (null !== $this->nonce) {
            $validator = $validator->claim(new NonceChecker($this->nonce));
        }

        if (null !== $this->maxAge) {
            $validator = $validator->claim(new AuthTimeChecker($this->maxAge, $this->clockTolerance));
        }

        if ((int) $this->maxAge > 0 || null !== $this->maxAge) {
            $mandatoryClaims[] = 'auth_time';
        }

        $validator = $validator->mandatory($mandatoryClaims);

        return $validator;
    }
}
