<?php

namespace Marketredesign\MrdAuth0Laravel\Auth\Guards;

use Illuminate\Auth\AuthManager;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Str;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

abstract class GuardAbstract implements Guard
{
    use GuardHelpers;

    protected ?string $expectedAudience;

    public function __construct(public string $name, protected ?array $config = null)
    {
        $this->expectedAudience = $config['audience'] ?? null;
    }

    public function getProvider()
    {
        if ($this->provider instanceof UserProvider) {
            return $this->provider;
        }

        $providerName = Str::of($this->config['provider'] ?? '')->trim();

        if ($providerName->isEmpty()) {
            throw new InvalidConfigurationException('No or empty provider name configured for auth guard.');
        }

        $provider = resolve(AuthManager::class)->createUserProvider($providerName);

        if ($provider instanceof UserProvider) {
            $this->provider = $provider;

            return $provider;
        }

        throw new InvalidConfigurationException('Configured user provider for auth guard is not available.');
    }
}
