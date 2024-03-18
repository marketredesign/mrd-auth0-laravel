<?php

namespace Marketredesign\MrdAuth0Laravel\Auth;

use Facile\JoseVerifier\JWK\JwksProviderBuilder;
use Facile\OpenIDClient\Client\ClientBuilder;
use Facile\OpenIDClient\Client\ClientInterface;
use Facile\OpenIDClient\Client\Metadata\ClientMetadata;
use Facile\OpenIDClient\Client\Metadata\ClientMetadataInterface;
use Facile\OpenIDClient\Issuer\IssuerBuilder;
use Facile\OpenIDClient\Issuer\Metadata\Provider\MetadataProviderBuilder;
use Illuminate\Support\Collection;
use Psr\Http\Client\ClientInterface as HttpClientInterface;

class OidcClientBuilder extends ClientBuilder
{
    protected Collection $config;

    protected ?HttpClientInterface $httpClient;

    public function __construct()
    {
        $this->config = collect(config('pricecypher-oidc'));
        $this->httpClient = config('pricecypher-oidc.http_client');
    }

    protected function getIssuerUrl(): ?string
    {
        if (!$this->config->get('issuer')) {
            return null;
        }

        return rtrim($this->config->get('issuer'), '/');
    }

    protected function getHttpClient(): ?\Psr\Http\Client\ClientInterface
    {
        return $this->config->get('http_client');
    }

    protected function getClientMetadata(): ClientMetadataInterface
    {
        return ClientMetadata::fromArray([
            'client_id' => $this->config->get('client_id'),
            'client_secret' => $this->config->get('client_secret'),
        ]);
    }

    protected function buildIssuer(): void
    {
        $issuerUrl = $this->getIssuerUrl();
        $issuerBuilder = new IssuerBuilder();
        // Custom builders are needed to be able to set a different HTTP client.
        // TODO cache?
        $metaProvBuilder = (new MetadataProviderBuilder())->setHttpClient($this->getHttpClient());
        $jwksProvBuilder = (new JwksProviderBuilder())->setHttpClient($this->getHttpClient());

        if (!$issuerUrl) {
            return;
        }

        $issuer = $issuerBuilder
            ->setMetadataProviderBuilder($metaProvBuilder)
            ->setJwksProviderBuilder($jwksProvBuilder)
            ->build("$issuerUrl/.well-known/openid-configuration");

        $this->setIssuer($issuer);
    }

    public function build(): ClientInterface
    {
        $this->buildIssuer();

        $this->setClientMetadata($this->getClientMetadata());
        $this->setHttpClient($this->getHttpClient());

        return parent::build();
    }
}
