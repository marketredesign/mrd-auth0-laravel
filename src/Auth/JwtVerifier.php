<?php

namespace Marketredesign\MrdAuth0Laravel\Auth;

use Facile\JoseVerifier\AbstractTokenVerifier;
use Throwable;

final class JwtVerifier extends AbstractTokenVerifier
{
    public function verify(string $jwt): array
    {
        $jwt = $this->decrypt($jwt);
        $validator = $this->create($jwt)->mandatory(['iss', 'sub', 'exp', 'iat']);

        // TODO validate aud claim.

        try {
            return $validator->run();
        } catch (Throwable $e) {
            throw $this->processException($e);
        }
    }
}
