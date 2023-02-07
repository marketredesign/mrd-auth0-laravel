<?php

namespace Marketredesign\MrdAuth0Laravel\Auth;

use Facile\JoseVerifier\Exception\InvalidTokenException;
use Facile\OpenIDClient\Client\ClientInterface;
use Facile\OpenIDClient\Token\AccessTokenVerifierBuilder;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Marketredesign\MrdAuth0Laravel\Auth\User\Provider;

class JwtGuard implements Guard
{
    use GuardHelpers;

    private ClientInterface $openIdClient;

    public function __construct(UserProvider $provider)
    {
        $this->setProvider($provider);

        $this->openIdClient = App::make(ClientInterface::class);
    }

    public function user()
    {
        if ($this->user !== null) {
            return $this->user;
        }

        if (!request() instanceof Request) {
            return null;
        }

        $verifierBuilder = new AccessTokenVerifierBuilder();
        $verifierBuilder->setJoseBuilder(new JoseBuilder());

        $tokenVerifier = $verifierBuilder->build($this->openIdClient);
        $token = request()->bearerToken();

        if (!is_string($token)) {
            return null;
        }

        try {
            $decodedJwt = $tokenVerifier->verify($token);
        } catch (InvalidTokenException $e) {
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

    public function validate(array $credentials = [])
    {
        return false;
    }

    public function hasScope(string $scope): bool
    {
        $userScopes = explode(' ', $this->user->scope ?? '');

        return in_array($scope, $userScopes, true);
    }
}
