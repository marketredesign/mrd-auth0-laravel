<?php

namespace Marketredesign\MrdAuth0Laravel\Auth;

use Facile\OpenIDClient\Client\ClientInterface;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Marketredesign\MrdAuth0Laravel\Auth\User\Provider;
use Marketredesign\MrdAuth0Laravel\Model\Stateful\User;

class OidcGuard implements Guard
{
    use GuardHelpers;

    private ClientInterface $openIdClient;

    public function __construct(UserProvider $provider)
    {
        $this->setProvider($provider);

        $this->openIdClient = App::make(ClientInterface::class);
    }

    public function logout()
    {
        request()->session()->remove('pc-oidc-session');
        Session::flush();

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
            abort('User model must implement Marketredesign\MrdAuth0Laravel\Model\Stateful\User');
        }

        return $this->user;
    }

    public function validate(array $credentials = [])
    {
        return false;
    }
}
