<?php

use Illuminate\Support\Facades\Route;
use Marketredesign\MrdAuth0Laravel\Http\Controller\Stateful\Callback;
use Marketredesign\MrdAuth0Laravel\Http\Controller\Stateful\Login;
use Marketredesign\MrdAuth0Laravel\Http\Controller\Stateful\Logout;

Route::group(['middleware' => ['web']], function () {
    Route::get('/oidc/callback', Callback::class)->name('oidc-callback');
    Route::get('/oidc/login', Login::class)->name('oidc-login');
    Route::get('/oidc/logout', Logout::class)->name('oidc-logout');
});
