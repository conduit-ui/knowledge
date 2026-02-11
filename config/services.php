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
    | Odin Sync Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for background sync with the Odin centralized Qdrant
    | server. Operations are queued locally and synced when Odin is available.
    | Last-write-wins conflict resolution based on updated_at timestamps.
    |
    */

    'odin' => [
        'enabled' => env('ODIN_SYNC_ENABLED', false),
        'url' => env('ODIN_URL'),
        'token' => env('ODIN_API_TOKEN', env('PREFRONTAL_API_TOKEN')),
        'timeout' => env('ODIN_TIMEOUT', 10),
        'batch_size' => env('ODIN_BATCH_SIZE', 50),
    ],

];
