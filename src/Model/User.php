<?php

namespace Marketredesign\MrdAuth0Laravel\Model;

use Illuminate\Contracts\Auth\Authenticatable;

class User implements Authenticatable
{
    private array $attributes;

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    public function __get(string $key)
    {
        return $this->attributes[$key] ?? null;
    }

    public function __set(string $key, $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function getAuthIdentifierName()
    {
        return 'sub';
    }

    public function getAuthIdentifier()
    {
        return $this->attributes['sub'] ?? $this->attributes['user_id'] ?? $this->attributes['email'] ?? null;
    }

    public function getAuthPassword()
    {
        return '';
    }

    public function getRememberToken()
    {
        return '';
    }

    public function setRememberToken($value)
    {
    }

    public function getRememberTokenName()
    {
        return '';
    }
}
