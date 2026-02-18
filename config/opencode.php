<?php

declare(strict_types=1);

return [
    'enabled' => env('OPENCODE_ENABLED', false),
    'url' => env('OPENCODE_URL'),
    'token' => env('OPENCODE_TOKEN'),
    'timeout' => (int) env('OPENCODE_TIMEOUT', 20),
];
