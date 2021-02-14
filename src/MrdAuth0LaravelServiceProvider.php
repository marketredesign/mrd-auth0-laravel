<?php

namespace Marketredesign\MrdAuth0Laravel;

use Auth0\Login\Contract\Auth0UserRepository;
use Auth0\SDK\API\Authentication;
use Auth0\SDK\API\Management;
use Illuminate\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Marketredesign\MrdAuth0Laravel\Contracts\UserRepository;
use Marketredesign\MrdAuth0Laravel\Http\Middleware\CheckJWT;

class MrdAuth0LaravelServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/mrd-auth0.php' => config_path('mrd-auth0.php'),
            ], 'mrd-auth0-config');
        }

        // Make the jwt middleware available to the router.
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('jwt', CheckJWT::class);
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        // Load our routes.
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        // Load our config.
        $this->mergeConfigFrom(__DIR__.'/../config/mrd-auth0.php', 'mrd-auth0');


        // Bind the auth0 user repository implementation.
        $this->app->bind(Auth0UserRepository::class, function (Application $app) {
            $config = $app['config']['mrd-auth0'];
            return new Repository\Auth0UserRepository($config['model'], $config['jwt-model']);
        });

        // Add a singleton for the Authentication SDK using laravel-auth0 config values to the service container.
        $this->app->singleton(Authentication::class, function () {
            $config = config('laravel-auth0');
            return new Authentication(
                $config['domain'],
                $config['client_id'],
                $config['client_secret'] ?? '',
                $config['api_identifier'] ?? '',
                null,
                $config['guzzle_options'] ?? []
            );
        });

        // Add a singleton for the Auth0 Management SDK using mrd-auth0 config for the management audience.
        $this->app->singleton(Management::class, function (Application $app) {
            $mrdConfig = config('mrd-auth0');
            $a0Config = config('laravel-auth0');
            $token = $app->make(Authentication::class)->client_credentials([
                'audience' => $mrdConfig['management_audience'],
            ]);
            $guzzleOptions = $a0Config['guzzle_options'] ?? [];

            return new Management($token['access_token'], $a0Config['domain'], $guzzleOptions, 'object');
        });

        // Bind the UserRepository implementation to the contract.
        $this->app->bind(UserRepository::class, \Marketredesign\MrdAuth0Laravel\Repository\UserRepository::class);
    }
}
