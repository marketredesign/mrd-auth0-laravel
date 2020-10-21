<?php


namespace Marketredesign\MrdAuth0Laravel\Http\Controllers;

use Auth0\Login\Auth0Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;

class Auth0IndexController extends Controller
{
    /**
     * Redirect to the Auth0 hosted login page
     *
     * @param  Auth0Service $auth0Service Auth0 service
     *
     * @return RedirectResponse
     */
    public function login(Auth0Service $auth0Service)
    {
        // If the user is already logged in, redirect them back to where they came from.
        if (Auth::check()) {
            return Redirect::back();
        }

        $authorizeParams = [
            'scope' => 'openid profile email',
        ];

        return $auth0Service->login(null, null, $authorizeParams);
    }

    /**
     * Log out of Auth0
     *
     * @return RedirectResponse
     */
    public function logout()
    {
        Auth::logout();

        $logoutUrl = sprintf(
            'https://%s/v2/logout?client_id=%s&returnTo=%s',
            config('laravel-auth0.domain'),
            config('laravel-auth0.client_id'),
            url('/')
        );

        return Redirect::to($logoutUrl);
    }
}
