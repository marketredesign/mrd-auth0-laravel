<?php

namespace Marketredesign\MrdAuth0Laravel;

use Auth0\Laravel\Contract\Event\Configuration\Building;
use Auth0\Laravel\Http\Middleware\Stateless\Authorize;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Marketredesign\MrdAuth0Laravel\Contracts\Auth0Repository;
use Marketredesign\MrdAuth0Laravel\Contracts\DatasetRepository;
use Marketredesign\MrdAuth0Laravel\Contracts\UserRepository;
use Marketredesign\MrdAuth0Laravel\Http\Middleware\AuthorizeDatasetAccess;
use Marketredesign\MrdAuth0Laravel\Http\Middleware\CheckPermissions;
use Marketredesign\MrdAuth0Laravel\Http\Middleware\SetRequestType;
use Marketredesign\MrdAuth0Laravel\Listeners\SetAuth0Strategy;

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

        $router = $this->app->make(Router::class);
        $kernel = $this->app->make(Kernel::class);

        // Make the permission and dataset middleware available to the router.
        $router->aliasMiddleware('dataset.access', AuthorizeDatasetAccess::class);
        $router->aliasMiddleware('permission', CheckPermissions::class);
        $router->aliasMiddleware('set.type', SetRequestType::class);

        // Ensure the Authorize middleware from Auth0 has a higher priority.
        $kernel->appendToMiddlewarePriority(Authorize::class);
        $kernel->appendToMiddlewarePriority(AuthorizeDatasetAccess::class);

        // Ensure the middleware to set the request type has highest priority.
        $kernel->prependToMiddlewarePriority(SetRequestType::class);

        // Set the request types for the web and api routes accordingly.
        $kernel->prependMiddlewareToGroup('web', 'set.type:stateful');
        $kernel->prependMiddlewareToGroup('api', 'set.type:stateless');

        // Listen to Auth0 SDK config building event to dynamically set the SDK strategy.
        Event::listen(Building::class, SetAuth0Strategy::class);
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
