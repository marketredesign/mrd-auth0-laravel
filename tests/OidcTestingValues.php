<?php

namespace Marketredesign\MrdAuth0Laravel\Tests;

use Firebase\JWT\JWT;
use Illuminate\Support\Str;
use OpenSSLAsymmetricKey;

use function Facile\OpenIDClient\base64url_encode;

trait OidcTestingValues
{
    protected string $oidcIssuer;

    protected OpenSSLAsymmetricKey $oidcPrivKey;

    protected string $oidcKid;

    protected function oidcTestingInit(): void
    {
        $this->oidcIssuer = 'https://domain.test';
        $this->oidcPrivKey = openssl_pkey_new(['digest_alg' => 'sha256', 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $this->oidcKid = 'kid_test';
    }

    protected function jwk(?string $kid = null, ?OpenSSLAsymmetricKey $privKey = null): array
    {
        if (! $kid || ! $privKey) {
            $kid = $this->oidcKid;
            $privKey = $this->oidcPrivKey;
        }

        $details = openssl_pkey_get_details($privKey);

        return [
            'kid' => $kid,
            'kty' => 'RSA',
            'alg' => 'RS256',
            'n' => base64url_encode($details['rsa']['n']),
            'e' => base64url_encode($details['rsa']['e']),
        ];
    }

    protected function openidConfig(?string $issuer = null): array
    {
        $issuer = $issuer ?? $this->oidcIssuer;

        return [
            'issuer' => $issuer,
            'authorization_endpoint' => "$issuer/authorize",
            'token_endpoint' => "$issuer/token",
            'jwks_uri' => "$issuer/jwks",
        ];
    }

    protected function openidJwksConfig(?string $kid = null, ?OpenSSLAsymmetricKey $privKey = null): array
    {
        return ['keys' => [$this->jwk($kid, $privKey)]];
    }

    protected function oidcIssuerUrl(string $path = '/', ?string $issuer = null): string
    {
        $issuer = rtrim($issuer ?? $this->oidcIssuer, '/');
        $path = ltrim($path, '/');

        return "$issuer/$path";
    }

    protected function encJwt(string $userId = 'test_user', array $claims = []): string
    {
        $payload = [
            'sub' => $userId,
            'custom:array' => [
                'abc',
                '123',
            ],
            'iss' => $this->oidcIssuer,
            'token_use' => 'access',
            'scope' => 'scope_a scope:b',
            'auth_time' => time(),
            'exp' => time() + 3600,
            'iat' => time(),
            'jti' => Str::uuid(),
            'username' => 'test_user',
            ...$claims,
        ];

        return JWT::encode($payload, $this->oidcPrivKey, 'RS256', $this->oidcKid);
    }
}
