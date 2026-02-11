<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Prefrontal Cortex API
    |--------------------------------------------------------------------------
    |
    | Configuration for the prefrontal-cortex cloud API used for knowledge
    | synchronization.
    |
    */

    'prefrontal' => [
        'url' => env('PREFRONTAL_API_URL'),
        'token' => env('PREFRONTAL_API_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Remote Sync Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for background sync with a centralized Qdrant
    | server. Operations are queued locally and synced when the remote server is available.
    | Last-write-wins conflict resolution based on updated_at timestamps.
    |
    */

    'remote' => [
        'enabled' => env('REMOTE_SYNC_ENABLED', false),
        'url' => env('REMOTE_SYNC_URL'),
        'token' => env('REMOTE_SYNC_TOKEN', env('PREFRONTAL_API_TOKEN')),
        'timeout' => env('REMOTE_SYNC_TIMEOUT', 10),
        'batch_size' => env('REMOTE_SYNC_BATCH_SIZE', 50),
    ],

];
