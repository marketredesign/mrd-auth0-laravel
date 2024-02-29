<?php

namespace Marketredesign\MrdAuth0Laravel\Auth\Guards;

use DomainException;
use Facile\JoseVerifier\TokenVerifierInterface;
use Facile\OpenIDClient\Client\ClientInterface;
use Facile\OpenIDClient\Token\AccessTokenVerifierBuilder;
use Firebase\JWT\JWTExceptionWithPayloadInterface;
use Illuminate\Auth\AuthManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Marketredesign\MrdAuth0Laravel\Auth\JoseBuilder;
use Marketredesign\MrdAuth0Laravel\Auth\User\Provider;
use UnexpectedValueException;

class JwtGuard extends GuardAbstract
{
    private TokenVerifierInterface $tokenVerifier;

    protected function init(AuthManager $auth, ?ClientInterface $oidcClient)
    {
        parent::init($auth, $oidcClient);

        $verifierBuilder = new AccessTokenVerifierBuilder();
        $verifierBuilder->setJoseBuilder(new JoseBuilder($this->expectedAudience));
        $this->tokenVerifier = $verifierBuilder->build($oidcClient);
    }

    public function user()
    {
        if ($this->user !== null) {
            return $this->user;
        }

        if (!request() instanceof Request) {
            return null;
        }

        $token = request()->bearerToken();

        if (!is_string($token)) {
            return null;
        }

        try {
            $decodedJwt = $this->tokenVerifier->verify($token);
        } catch (DomainException|JWTExceptionWithPayloadInterface|UnexpectedValueException $e) {
            Log::debug('JWT decoding failed, request not authorized.', $e->getTrace());
            return null;
        }

        $userProvider = $this->getProvider();

        if ($userProvider instanceof Provider) {
            $this->user = $userProvider->getRepository()->fromAccessToken($decodedJwt);
        } else {
            $this->user = $userProvider->retrieveById($decodedJwt['sub']);
        }

        return $this->user;
    }

    public function validate(array $credentials = []): bool
    {
        return false;
    }

    public function hasScope(string $scope): bool
    {
        $scopeString = $this->user->scope ?? $this->user->scp ?? '';
        $userScopes = explode(' ', $scopeString);

        return in_array($scope, $userScopes, true);
    }
}
