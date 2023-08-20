<?php

return [
    /*
    |-------------------------------------------------------------------------------------------------------------------
    | TODO
    |-------------------------------------------------------------------------------------------------------------------
    | TODO
    |
    */
    'issuer' => env('OIDC_ISSUER'),

    'client_id' => env('OIDC_CLIENT_ID'),
    'client_secret' => env('OIDC_CLIENT_SECRET'),

    'audience' => env('OIDC_AUDIENCE', 'https://api.pricecypher.com'),

    'logout_endpoint' => env('OIDC_LOGOUT_ENDPOINT'),

    'scope_prefix' => env('OIDC_SCOPE_PREFIX', ''),

    'id_scopes' => explode(' ', env('OIDC_ID_SCOPES', 'openid')),

    'routes' => [
        'home' => env('OIDC_ROUTE_HOME', '/'),
    ],
];
