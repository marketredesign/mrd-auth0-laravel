<?php

namespace Marketredesign\MrdAuth0Laravel\Auth\Guards;

use Facile\OpenIDClient\Client\ClientInterface;
use Illuminate\Auth\AuthManager;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;

abstract class GuardAbstract implements Guard
{
    use GuardHelpers;

    protected ?string $expectedAudience;

    public function __construct(public string $name, protected ?array $config = null)
    {
        App::call([$this, 'init']);
    }

    public function init(AuthManager $auth, ?ClientInterface $oidcClient)
    {
        if (!$oidcClient) {
            return null;
        }

        $this->provider = $auth->createUserProvider($this->config['provider']);
        $this->expectedAudience = Config::get('pricecypher-oidc.audience');

        return $this;
    }
}
