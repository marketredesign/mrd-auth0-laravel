<?php

namespace Marketredesign\MrdAuth0Laravel;

use Facile\JoseVerifier\TokenVerifierInterface;
use Facile\OpenIDClient\Authorization\AuthRequest;
use Facile\OpenIDClient\Authorization\AuthRequestInterface;
use Facile\OpenIDClient\Client\ClientInterface;
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
use Marketredesign\MrdAuth0Laravel\Auth\Guards\JwtGuard;
use Marketredesign\MrdAuth0Laravel\Auth\Guards\OidcGuard;
use Marketredesign\MrdAuth0Laravel\Auth\JoseBuilder;
use Marketredesign\MrdAuth0Laravel\Auth\OidcClientBuilder;
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

        $auth->extend('pc-jwt', static fn($app, $name, array $config) => new JwtGuard($name, $config));
        $auth->extend('pc-oidc', static fn($app, $name, array $config) => new OidcGuard($name, $config));
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

        $this->app->singleton(ClientInterface::class, static fn() => (new OidcClientBuilder())->build());

        $this->app->singleton(
            AuthorizationService::class,
            static fn() => (new AuthorizationServiceBuilder())->build(),
        );

        $this->app->singleton(TokenVerifierInterface::class, function () {
            $verifierBuilder = new AccessTokenVerifierBuilder();
            $joseBuilder = new JoseBuilder(config('pricecypher-oidc.audience'));

            $verifierBuilder->setJoseBuilder($joseBuilder);

            return $verifierBuilder->build(resolve(ClientInterface::class));
        });

        $this->app->bind(AuthRequestInterface::class, static fn() => AuthRequest::fromParams([
            'client_id' => config('pricecypher-oidc.client_id'),
            'redirect_uri' => route('oidc-callback'),
            'scope' => config('pricecypher-oidc.id_scopes'),
        ]));
    }
}
