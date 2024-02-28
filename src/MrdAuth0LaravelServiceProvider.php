<?php

namespace Marketredesign\MrdAuth0Laravel;

use Facile\JoseVerifier\JWK\JwksProviderBuilder;
use Facile\OpenIDClient\Authorization\AuthRequest;
use Facile\OpenIDClient\Authorization\AuthRequestInterface;
use Facile\OpenIDClient\Client\ClientBuilder;
use Facile\OpenIDClient\Client\ClientInterface;
use Facile\OpenIDClient\Client\Metadata\ClientMetadata;
use Facile\OpenIDClient\Issuer\IssuerBuilder;
use Facile\OpenIDClient\Issuer\Metadata\Provider\MetadataProviderBuilder;
use Facile\OpenIDClient\Service\AuthorizationService;
use Facile\OpenIDClient\Service\Builder\AuthorizationServiceBuilder;
use Facile\OpenIDClient\Token\AccessTokenVerifierBuilder;
use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\ServiceProvider;
use Marketredesign\MrdAuth0Laravel\Auth\JoseBuilder;
use Marketredesign\MrdAuth0Laravel\Auth\JwtGuard;
use Marketredesign\MrdAuth0Laravel\Auth\OidcGuard;
use Marketredesign\MrdAuth0Laravel\Auth\User\Provider;
use Marketredesign\MrdAuth0Laravel\Contracts\AuthRepository;
use Marketredesign\MrdAuth0Laravel\Contracts\DatasetRepository;
use Marketredesign\MrdAuth0Laravel\Contracts\UserRepository;
use Marketredesign\MrdAuth0Laravel\Facades\PricecypherAuth;
use Marketredesign\MrdAuth0Laravel\Http\Middleware\AuthenticateOidc;
use Marketredesign\MrdAuth0Laravel\Http\Middleware\AuthorizeDatasetAccess;
use Marketredesign\MrdAuth0Laravel\Http\Middleware\AuthorizeJwt;
use Marketredesign\MrdAuth0Laravel\Http\Middleware\CheckPermissions;

class MrdAuth0LaravelServiceProvider extends ServiceProvider
{
    private function extendAuthJwt(AuthManager $auth): void
    {
        $auth->extend(
            'pc-jwt',
            function ($app, $name, array $config) use ($auth) {
                $oidcClient = $app->make(ClientInterface::class);

                if (!$oidcClient) {
                    return null;
                }

                $provider = $auth->createUserProvider($config['provider']);
                $audience = $app['config']->get('pricecypher-oidc.audience');

                $verifierBuilder = new AccessTokenVerifierBuilder();
                $verifierBuilder->setJoseBuilder(new JoseBuilder($audience));
                $tokenVerifier = $verifierBuilder->build($oidcClient);

                $guard = new JwtGuard($tokenVerifier);

                return $guard->withProvider($provider)->withExpectedAudience($audience);
            }
        );
    }

    private function extendAuthOidc(AuthManager $auth): void
    {
        $auth->extend(
            'pc-oidc',
            function ($app, $name, array $config) use ($auth) {
                $oidcClient = $app->make(ClientInterface::class);

                if (!$oidcClient) {
                    return null;
                }

                $provider = $auth->createUserProvider($config['provider']);
                $audience = $app['config']->get('pricecypher-oidc.audience');
                $guard = new OidcGuard();

                return $guard->withProvider($provider)->withExpectedAudience($audience);
            }
        );
    }

    private function httpMacros(): void
    {
        $getAccessToken = function () {
            if (App::runningInConsole()) {
                return PricecypherAuth::getMachineToMachineToken();
            }

            return Request::bearerToken();
        };

        Http::macro('userTool', function () use ($getAccessToken) {
            return Http::baseUrl(config('pricecypher.services.user_tool'))
                ->withToken($getAccessToken())
                ->acceptJson();
        });
    }

    /**
     * Bootstrap the application services.
     */
    public function boot(AuthManager $auth)
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/mrd-auth0.php' => config_path('mrd-auth0.php'),
                __DIR__ . '/../config/pricecypher.php' => config_path('pricecypher.php'),
                __DIR__ . '/../config/pricecypher-oidc.php' => config_path('pricecypher-oidc.php'),
            ], 'mrd-auth0-config');
        }

        $this->extendAuthJwt($auth);
        $this->extendAuthOidc($auth);
        $auth->provider('pc-users', fn() => new Provider());

        $router = $this->app->make(Router::class);
        $kernel = $this->app->make(Kernel::class);

        // Make the permission and dataset middleware available to the router.
        $router->aliasMiddleware('dataset.access', AuthorizeDatasetAccess::class);
        $router->aliasMiddleware('permission', CheckPermissions::class);
        $router->aliasMiddleware('jwt', AuthorizeJwt::class);
        $router->aliasMiddleware('oidc', AuthenticateOidc::class);

        // Ensure the Authorize middleware from Auth0 has a higher priority.
        $kernel->appendToMiddlewarePriority(AuthorizeJwt::class);
        $kernel->appendToMiddlewarePriority(AuthorizeDatasetAccess::class);

        $this->httpMacros();
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        // Load our routes.
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        // Load our configs.
        $this->mergeConfigFrom(__DIR__ . '/../config/mrd-auth0.php', 'mrd-auth0');
        $this->mergeConfigFrom(__DIR__ . '/../config/pricecypher.php', 'pricecypher');
        $this->mergeConfigFrom(__DIR__ . '/../config/pricecypher-oidc.php', 'pricecypher-oidc');

        // Bind repository implementations to the contracts.
        $this->app->bind(AuthRepository::class, Repository\AuthRepository::class);
        $this->app->bind(DatasetRepository::class, Repository\DatasetRepository::class);
        $this->app->bind(UserRepository::class, Repository\UserRepository::class);

        $this->app->singleton(ClientInterface::class, function () {
            $issUrl = rtrim(config('pricecypher-oidc.issuer'), '/');

            if (!$issUrl) {
                return null;
            }

            // Custom builders are needed to be able to set a different HTTP client.
            $metaProvBuilder = (new MetadataProviderBuilder())->setHttpClient(config('pricecypher-oidc.http_client'));
            $jwksProvBuilder = (new JwksProviderBuilder())->setHttpClient(config('pricecypher-oidc.http_client'));
            $issuer = (new IssuerBuilder())
                ->setMetadataProviderBuilder($metaProvBuilder)
                ->setJwksProviderBuilder($jwksProvBuilder)
                ->build("$issUrl/.well-known/openid-configuration");

            $clientMetadata = ClientMetadata::fromArray([
                'client_id' => config('pricecypher-oidc.client_id'),
                'client_secret' => config('pricecypher-oidc.client_secret'),
            ]);

            return (new ClientBuilder())
                ->setIssuer($issuer)
                ->setClientMetadata($clientMetadata)
                ->setHttpClient(config('pricecypher-oidc.http_client'))
                ->build();
        });

        $this->app->singleton(AuthorizationService::class, function () {
            return (new AuthorizationServiceBuilder())->build();
        });

        $this->app->bind(AuthRequestInterface::class, function () {
            return AuthRequest::fromParams([
                'client_id' => config('pricecypher-oidc.client_id'),
                'redirect_uri' => route('oidc-callback'),
                'scope' => config('pricecypher-oidc.id_scopes'),
            ]);
        });
    }
}
