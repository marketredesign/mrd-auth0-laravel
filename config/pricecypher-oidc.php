<?php

use Illuminate\Support\Str;

return [
    /*
    |-------------------------------------------------------------------------------------------------------------------
    | OpenID Connect Issuer
    |-------------------------------------------------------------------------------------------------------------------
    | Entity that issues a set of claims. It should contain a Discovery metadata document at the following path:
    | `/.well-known/openid-configuration`.
    |
    */
    'issuer' => env('OIDC_ISSUER'),

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

    /*
    |-------------------------------------------------------------------------------------------------------------------
    | Access Tokens Audience
    |-------------------------------------------------------------------------------------------------------------------
    | The resource identifier (i.e. a single 'logical' grouping of all PriceCypher services that present APIs) that must
    | be present in the `aud` claim of access tokens for those tokens to be considered valid.
    | NB: The required audience for ID tokens is not configurable. After all, ID tokens are only intended to be used by
    | the web app that requested the token. Hence, for an ID token to pass verification, the client ID of the web app
    | itself must be present in the `aud` claim of the ID token.
    |
    */
    'audience' => env('OIDC_AUDIENCE', 'https://api.pricecypher.com'),

    /*
    |-------------------------------------------------------------------------------------------------------------------
    | OpenID RP-Initiated Logout
    |-------------------------------------------------------------------------------------------------------------------
    | Endpoint where the users should be redirected to when they initiate a logout, after we have ended their current
    | session within this web app.
    |
    | NB: this URL is normally obtained via the `end_session_endpoint` element of the issuer's Discovery response.
    | Hence, this configuration option should only be used if that element is not part of the Discovery already.
    */
    'logout_endpoint' => env('OIDC_LOGOUT_ENDPOINT'),

    /*
    |-------------------------------------------------------------------------------------------------------------------
    | OpenID Scope Prefix
    |-------------------------------------------------------------------------------------------------------------------
    | Optional prefix used during the scope authorization checks of Access Tokens. A required scope 's', as defined by
    | the Resource Provider, is considered valid for a given Access Token if, and only if, the `scope` (or `scp`) claim
    | of the Access Token contains the scope `s` prepended with this configured scope prefix.
    |
    */
    'scope_prefix' => env('OIDC_SCOPE_PREFIX', ''),

    /*
    |-------------------------------------------------------------------------------------------------------------------
    | OpenID Authentication Request Scopes
    |-------------------------------------------------------------------------------------------------------------------
    | Scopes (separated by spaces) that will be requested as part of the OpenID Connect Authentication Requests (i.e.
    | when a web app requests that an End-User be authenticated by the Authorization Server). Typically, the OIDC
    | Provider allows some additional claims (e.g. `profile`, `email`, etc.) to be included in the ID token responses
    | using this scopes parameter.
    |
    | NB: As defined in the OIDC specs, the `openid` scope must always be included in authentication requests. The
    | default config value computation of `id_scopes` first reads the space-separated scopes from an env var and adds
    | the `openid` scope to the config value if it is not already included.
    */
    'id_scopes' => Str::of(env('OIDC_ID_SCOPES', ''))->explode(' ')->push('openid')->unique()->filter()->join(' '),

    /*
    |-------------------------------------------------------------------------------------------------------------------
    | Application routes used by some OIDC flows.
    |-------------------------------------------------------------------------------------------------------------------
    | This config key is only intended to be used for routes within the web app being configured here. So for instance,
    | the OpenID RP-Initiated Logout endpoint has explicitly not been made part of the routes here to avoid confusion.
    | This, as that endpoint is provided by the OIDC Provider app instead. In fact, this package already defines a
    | logout route to be used by this web app only (and redirects the user to the OIDC Provider's endpoint at the end).
    */
    'routes' => [
        'home' => env('OIDC_ROUTE_HOME', '/'),
    ],

    /*
    |-------------------------------------------------------------------------------------------------------------------
    | HTTP client used for OIDC calls.
    |-------------------------------------------------------------------------------------------------------------------
    | Optionally, a PSR-18 compliant HTTP client can be specified to be used for requests to the OIDC Provider.
    | Defaults to using the PSR-18 HTTP Client Discovery service to find a suitable HTTP Client implementation.
    |
    | NB: For instance, could come in handy during automated tests, to mock the underlying HTTP responses.
    */
    'http_client' => null,
];
