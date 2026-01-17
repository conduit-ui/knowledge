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
        'url' => env('PREFRONTAL_API_URL', 'http://100.68.122.24:8080'),
        'token' => env('PREFRONTAL_API_TOKEN'),
    ],

];
