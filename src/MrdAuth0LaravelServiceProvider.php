<?php

namespace Marketredesign\MrdAuth0Laravel;

use Auth0\Laravel\Http\Middleware\Stateless\Authorize;
use Facile\OpenIDClient\Authorization\AuthRequest;
use Facile\OpenIDClient\Authorization\AuthRequestInterface;
use Facile\OpenIDClient\Client\ClientBuilder;
use Facile\OpenIDClient\Client\ClientInterface;
use Facile\OpenIDClient\Client\Metadata\ClientMetadata;
use Facile\OpenIDClient\Issuer\IssuerBuilder;
use Facile\OpenIDClient\Service\AuthorizationService;
use Facile\OpenIDClient\Service\Builder\AuthorizationServiceBuilder;
use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Marketredesign\MrdAuth0Laravel\Auth\JwtGuard;
use Marketredesign\MrdAuth0Laravel\Auth\OidcGuard;
use Marketredesign\MrdAuth0Laravel\Auth\User\Provider;
use Marketredesign\MrdAuth0Laravel\Contracts\Auth0Repository;
use Marketredesign\MrdAuth0Laravel\Contracts\DatasetRepository;
use Marketredesign\MrdAuth0Laravel\Contracts\UserRepository;
use Marketredesign\MrdAuth0Laravel\Http\Middleware\AuthenticateOidc;
use Marketredesign\MrdAuth0Laravel\Http\Middleware\AuthorizeDatasetAccess;
use Marketredesign\MrdAuth0Laravel\Http\Middleware\AuthorizeJwt;
use Marketredesign\MrdAuth0Laravel\Http\Middleware\CheckPermissions;

class MrdAuth0LaravelServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot(AuthManager $auth)
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/mrd-auth0.php' => config_path('mrd-auth0.php'),
                __DIR__ . '/../config/pricecypher-oidc.php' => config_path('pricecypher-oidc.php'),
            ], 'mrd-auth0-config');
        }

        $auth->extend(
            'pc-jwt',
            fn ($app, $name, array $config) => new JwtGuard($auth->createUserProvider($config['provider']))
        );
        $auth->extend(
            'pc-oidc',
            fn ($app, $name, array $config) => new OidcGuard($auth->createUserProvider($config['provider']))
        );
        $auth->provider('pc-users', fn () => new Provider());

        $router = $this->app->make(Router::class);
        $kernel = $this->app->make(Kernel::class);

        // Make the permission and dataset middleware available to the router.
        $router->aliasMiddleware('dataset.access', AuthorizeDatasetAccess::class);
        $router->aliasMiddleware('permission', CheckPermissions::class);
        $router->aliasMiddleware('jwt', AuthorizeJwt::class);
        $router->aliasMiddleware('oidc', AuthenticateOidc::class);

        // Ensure the Authorize middleware from Auth0 has a higher priority.
        $kernel->appendToMiddlewarePriority(Authorize::class);
        $kernel->appendToMiddlewarePriority(AuthorizeJwt::class);
        $kernel->appendToMiddlewarePriority(AuthorizeDatasetAccess::class);
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
        $this->mergeConfigFrom(__DIR__ . '/../config/pricecypher-oidc.php', 'pricecypher-oidc');

        // Bind repository implementations to the contracts.
        $this->app->bind(Auth0Repository::class, Repository\Auth0Repository::class);
        $this->app->bind(DatasetRepository::class, Repository\DatasetRepository::class);
        $this->app->bind(UserRepository::class, Repository\UserRepository::class);

        $this->app->singleton(ClientInterface::class, function () {
            $issuer = (new IssuerBuilder())->build(
                rtrim(config('pricecypher-oidc.issuer'), '/') . '/.well-known/openid-configuration'
            );
            $clientMetadata = ClientMetadata::fromArray([
                'client_id' => config('pricecypher-oidc.client_id'),
                'client_secret' => config('pricecypher-oidc.client_secret'),
            ]);

            return (new ClientBuilder())
                ->setIssuer($issuer)
                ->setClientMetadata($clientMetadata)
                ->build();
        });

        $this->app->singleton(AuthorizationService::class, function () {
            return (new AuthorizationServiceBuilder())->build();
        });

        $this->app->bind(AuthRequestInterface::class, function () {
            return AuthRequest::fromParams([
                'client_id' => config('pricecypher-oidc.client_id'),
                'redirect_uri' => route('oidc-callback'),
                'scope' => config('pricecpyher-oidc.id_scopes'),
            ]);
        });
    }
}
