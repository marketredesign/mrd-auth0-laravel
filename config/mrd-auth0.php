<?php

return [
    /*
    |-------------------------------------------------------------------------------------------------------------------
    | Permissions claim property name
    |-------------------------------------------------------------------------------------------------------------------
    | Property name of the custom permissions claim that is included in the ID tokens issued by Auth0.
    |
    */
    'permissions_claim' => 'https://marketredesign.com/permissions',

    /*
    |-------------------------------------------------------------------------------------------------------------------
    | Cache TTL
    |-------------------------------------------------------------------------------------------------------------------
    | Time to live for cache entries stored by the package, in seconds. E.g. user info in Request and UserRepository.
    |
    */
    'cache_ttl' => env('AUTH0_CACHE_TTL', 300),

    /*
    |-------------------------------------------------------------------------------------------------------------------
    | Chunk size
    |-------------------------------------------------------------------------------------------------------------------
    | Size of chunks when requesting values from the Auth0 management API. E.g. number of users in UserRepository.
    |
    */
    'chunk_size' => env('AUTH0_CHUNK_SIZE', 50),

    /*
    |-------------------------------------------------------------------------------------------------------------------
    | User Tool URL
    |-------------------------------------------------------------------------------------------------------------------
    | Base URL of the PriceCypher User Tool.
    |
    */
    'user_tool_url' => env('USER_TOOL_URL', 'https://users.pricecypher.com/api'),

    /*
    |-------------------------------------------------------------------------------------------------------------------
    | Guzzle Options
    |-------------------------------------------------------------------------------------------------------------------
    | guzzle_options (array). Used to specify additional connection options e.g. proxy settings.
    |
    */
    'guzzle_options' => [],

    /*
    |-------------------------------------------------------------------------------------------------------------------
    | Connection
    |-------------------------------------------------------------------------------------------------------------------
    | relationship between Auth0 and a source of users.
    */
    'connection' => env('AUTH0_CONNECTION', "External")
];
