<?php

namespace Marketredesign\MrdAuth0Laravel;

use Illuminate\Support\ServiceProvider;

class MrdAuth0LaravelServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        //
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        // Bind the auth0 user repository implementation.
        $this->app->bind(
            \Auth0\Login\Contract\Auth0UserRepository::class,
            \Auth0\Login\Repository\Auth0UserRepository::class
        );

        // Load our routes
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
    }
}
