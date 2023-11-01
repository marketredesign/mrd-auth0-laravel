<?php

return [
    /*
    |-------------------------------------------------------------------------------------------------------------------
    | OpenID Connect Issuer
    |-------------------------------------------------------------------------------------------------------------------
    | Entity that issues a set of claims. It should contain a metadata document at /.well-known/openid-configuration.
    |
    */
    'issuer' => env('OIDC_ISSUER'),

    /*
    |-------------------------------------------------------------------------------------------------------------------
    | OpenID Connect Issuer Identifier
    |-------------------------------------------------------------------------------------------------------------------
    | Verifiable identifier for an issuer. An issuer identifier is a case-sensitive URL that uses the HTTPS scheme that
    | contains scheme, host, and optionally, port number and path components and no query or fragment components.
    | It must match the `iss` claim of tokens, for them to be considered valid. This value is generally the same as
    | the OIDC Issuer configured above.
    | TODO: check if needed?
    |
    */
    // 'issuer_identifier' => env('OIDC_ISSUER_IDENTIFIER', env('OIDC_ISSUER')),

    /*
    |-------------------------------------------------------------------------------------------------------------------
    | OpenID Connect Client ID
    |-------------------------------------------------------------------------------------------------------------------
    | Identity of the OIDC client.
    |
    */
    'client_id' => env('OIDC_CLIENT_ID'),

    /*
    |-------------------------------------------------------------------------------------------------------------------
    | OpenID Connect Client Secret
    |-------------------------------------------------------------------------------------------------------------------
    | Secret key of the OIDC client.
    |
    */
    'client_secret' => env('OIDC_CLIENT_SECRET'),

    'audience' => env('OIDC_AUDIENCE', 'https://api.pricecypher.com'),

    'logout_endpoint' => env('OIDC_LOGOUT_ENDPOINT'),

    'scope_prefix' => env('OIDC_SCOPE_PREFIX', ''),

    'id_scopes' => env('OIDC_ID_SCOPES', 'openid'),

    'routes' => [
        'home' => env('OIDC_ROUTE_HOME', '/'),
    ],

    'http_client' => null,
];