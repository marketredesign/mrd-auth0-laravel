<?php

namespace Marketredesign\MrdAuth0Laravel\Auth;

use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Marketredesign\MrdAuth0Laravel\Auth\User\Provider;
use Marketredesign\MrdAuth0Laravel\Model\Stateful\User;

class OidcGuard implements Guard
{
    use GuardHelpers;

    private ?string $expectedAudience;

    public function __construct()
    {
        $this->expectedAudience = null;
    }

    public function withProvider(UserProvider $provider): OidcGuard
    {
        $this->setProvider($provider);
        return $this;
    }

    public function withExpectedAudience(string $audience): OidcGuard
    {
        $this->expectedAudience = $audience;
        return $this;
    }

    public function logout()
    {
        request()->session()->remove('pc-oidc-session');
        Session::flush();
        $this->user = null;

        return $this;
    }

    public function user()
    {
        if ($this->user !== null) {
            return $this->user;
        }

        if (!request() instanceof Request) {
            return null;
        }

        $session = request()->session()->get('pc-oidc-session');

        if ($session === null) {
            return null;
        }

        $userProvider = $this->getProvider();

        if ($userProvider instanceof Provider) {
            $this->user = $userProvider->getRepository()->fromSession($session->user);
        } else {
            $this->user = $userProvider->retrieveById($session->user->sub);
        }

        if ($this->user === null) {
            return null;
        }

        if (!($this->user instanceof User)) {
            abort('User model must implement "' . User::class . '"');
        }

        return $this->user;
    }

    public function validate(array $credentials = [])
    {
        return false;
    }
}
