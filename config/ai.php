<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI usage limits
    |--------------------------------------------------------------------------
    |
    | Guards against runaway cost. Rate limits are enforced per user per hour by
    | the named limiters in AppServiceProvider; the daily quota is checked in
    | AiUsageLogger before each operation and counts successful runs only.
    |
    */

    'limits' => [
        'per_hour' => (int) env('AI_RATE_PER_HOUR', 10),
        'uploads_per_hour' => (int) env('AI_UPLOADS_PER_HOUR', 20),
        'exports_per_hour' => (int) env('AI_EXPORTS_PER_HOUR', 30),
        'daily_quota' => (int) env('AI_DAILY_LIMIT', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue health thresholds
    |--------------------------------------------------------------------------
    |
    | Used by GET /health/queue and `php artisan app:queue-health` to decide
    | whether the queue is degraded.
    |
    */

    'health' => [
        'max_pending' => (int) env('QUEUE_MAX_PENDING', 100),
        'max_oldest_seconds' => (int) env('QUEUE_MAX_OLDEST_SECONDS', 600),
    ],

];
