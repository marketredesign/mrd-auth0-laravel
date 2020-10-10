<?php

use Auth0\Login\Auth0Controller;
use Illuminate\Support\Facades\Route;
use Marketredesign\MrdAuth0Laravel\Http\Auth0IndexController;

Route::group(['middleware' => ['web']], function () {
    Route::get('/auth0/callback', [Auth0Controller::class, 'callback'])->name('auth0-callback');
    Route::get('/login', [Auth0IndexController::class, 'login'])->name('login');
    Route::middleware('auth')->get('/logout', [Auth0IndexController::class, 'logout'])->name('logout');
});
