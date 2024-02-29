<?php

// TODO remove file when User Repo refactored / removed.
return [
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
    | Connection
    |-------------------------------------------------------------------------------------------------------------------
    | relationship between Auth0 and a source of users.
    */
    'connection' => env('AUTH0_CONNECTION', "External")
];
