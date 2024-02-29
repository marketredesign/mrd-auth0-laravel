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

    /**
     * @inheritDocs
     */
    public function getAuthIdentifierName(): string
    {
        return 'sub';
    }

    /**
     * @inheritDocs
     */
    public function getAuthIdentifier(): mixed
    {
        return $this->attributes['sub'] ?? $this->attributes['user_id'] ?? $this->attributes['email'] ?? null;
    }

    /**
     * @inheritDocs
     */
    public function getAuthPassword(): string
    {
        return '';
    }

    /**
     * @inheritDocs
     */
    public function getRememberToken(): string
    {
        return '';
    }

    /**
     * @inheritDocs
     */
    public function setRememberToken($value): void
    {
    }

    /**
     * @inheritDocs
     */
    public function getRememberTokenName(): string
    {
        return '';
    }
}
