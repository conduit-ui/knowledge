<?php

declare(strict_types=1);

return [

    'url' => env('QDRANT_URL', 'http://localhost:6333'),

    'api_key' => env('QDRANT_API_KEY'),

    'timeout' => [
        'connect' => (int) env('QDRANT_CONNECT_TIMEOUT', 10),
        'request' => (int) env('QDRANT_REQUEST_TIMEOUT', 30),
    ],

];
