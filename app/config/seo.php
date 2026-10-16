<?php

return [
    'mode' => env('DATAFORSEO_MODE', 'mock'),
    'live_enabled' => (bool) env('DATAFORSEO_LIVE_ENABLED', false),
    'live_approval' => env('DATAFORSEO_LIVE_APPROVAL', ''),
    'login' => env('DATAFORSEO_LOGIN', ''),
    'password' => env('DATAFORSEO_PASSWORD', ''),
    'location_code' => 2792,
    'language_code' => 'tr',
    'timeout' => 30,
    'connect_timeout' => 5,
    'rate_limit' => 30,
    'cache_seconds' => 86400,
    'row_limit' => 1000,
    'monthly_budget_micros' => 10000000, // $10; independent ledgers for mock and live.
    'request_ceiling_micros' => 100000, // $0.10 conservative reservation, not a provider price quote.
    'safe_retries' => 2,
];
