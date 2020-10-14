<?php

namespace Marketredesign\MrdAuth0Laravel;

use Auth0\Login\Contract\Auth0UserRepository;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;

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
    }
}
