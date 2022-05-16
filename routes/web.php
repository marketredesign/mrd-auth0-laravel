<?php

use Auth0\Laravel\Http\Controller\Stateful\Callback;
use Auth0\Laravel\Http\Controller\Stateful\Login;
use Auth0\Laravel\Http\Controller\Stateful\Logout;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['web']], function () {
    Route::get('/auth0/callback', Callback::class)->name('auth0-callback');
    Route::get('/login', Login::class)->name('login');
    Route::get('/logout', Logout::class)->name('logout');
});
