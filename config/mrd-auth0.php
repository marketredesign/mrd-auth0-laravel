<?php

use Auth0\Login\Auth0JWTUser;
use Auth0\Login\Auth0User;

return [
    /*
    |-------------------------------------------------------------------------------------------------------------------
    | User Model
    |-------------------------------------------------------------------------------------------------------------------
    |
    | Configure which user model is used by the Auth0 Laravel plugin.
    |
    */

    'model' => Auth0User::class,

    /*
    |-------------------------------------------------------------------------------------------------------------------
    | JWT Model
    |-------------------------------------------------------------------------------------------------------------------
    |
    | Configure which JWT model is used by the Auth0 Laravel plugin.
    |
    */

    'jwt-model' => Auth0JWTUser::class,

    /*
    |-------------------------------------------------------------------------------------------------------------------
    |   Management API audience
    |-------------------------------------------------------------------------------------------------------------------
    |   The API audience of the Auth0 Management API, as set in the auth0 administration page
    |
    */

    'management_audience' => env('AUTH0_MANAGEMENT_AUDIENCE'),
];
