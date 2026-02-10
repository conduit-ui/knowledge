<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Staging Retention Period
    |--------------------------------------------------------------------------
    |
    | Number of days entries sit in the daily log staging area before they
    | become eligible for promotion to permanent storage.
    |
    */

    'retention_days' => env('STAGING_RETENTION_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Auto-Promote Configuration
    |--------------------------------------------------------------------------
    |
    | Entries matching an existing category with confidence >= threshold
    | will be automatically promoted during 'know promote --auto'.
    |
    */

    'auto_promote_confidence' => env('STAGING_AUTO_PROMOTE_CONFIDENCE', 80),
];
