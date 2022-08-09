<?php

namespace Marketredesign\MrdAuth0Laravel;

use Auth0\Laravel\Http\Middleware\Stateless\Authorize;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Marketredesign\MrdAuth0Laravel\Contracts\Auth0Repository;
use Marketredesign\MrdAuth0Laravel\Contracts\DatasetRepository;
use Marketredesign\MrdAuth0Laravel\Contracts\UserRepository;
use Marketredesign\MrdAuth0Laravel\Http\Middleware\AuthorizeDatasetAccess;
use Marketredesign\MrdAuth0Laravel\Http\Middleware\CheckPermissions;

class MrdAuth0LaravelServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/mrd-auth0.php' => config_path('mrd-auth0.php'),
            ], 'mrd-auth0-config');
        }

        // Make the permission and dataset middleware available to the router.
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('dataset.access', AuthorizeDatasetAccess::class);
        $router->aliasMiddleware('permission', CheckPermissions::class);

        // Make sure the Authorize middleware from Auth0 has a higher priority.
        $kernel = $this->app->make(Kernel::class);
        $kernel->appendToMiddlewarePriority(Authorize::class);
        $kernel->appendToMiddlewarePriority(AuthorizeDatasetAccess::class);
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        // Load our routes.
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        // Load our config.
        $this->mergeConfigFrom(__DIR__ . '/../config/mrd-auth0.php', 'mrd-auth0');

        // Bind repository implementations to the contracts.
        $this->app->bind(Auth0Repository::class, Repository\Auth0Repository::class);
        $this->app->bind(DatasetRepository::class, Repository\DatasetRepository::class);
        $this->app->bind(UserRepository::class, Repository\UserRepository::class);
    }
}
