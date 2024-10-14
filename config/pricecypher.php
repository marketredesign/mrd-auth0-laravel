<?php

return [
    /*
    |-------------------------------------------------------------------------------------------------------------------
    | PriceCypher back-end services base URLs.
    |-------------------------------------------------------------------------------------------------------------------
    | Hostnames of the PriceCypher back-end services.
    |
    */
    'services' => [
        'user_tool' => env('BASE_USERS', 'https://users.pricecypher.com'),
    ],

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
    | Cache TTL
    |-------------------------------------------------------------------------------------------------------------------
    | Time to live for cache entries stored by the package, in seconds. E.g. user info in Request and UserRepository.
    |
    */
    'cache_ttl' => env('PC_CACHE_TTL', 300),
];
