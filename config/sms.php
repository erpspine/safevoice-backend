<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SMS Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for SMS service integration
    |
    */

    'default' => env('SMS_DRIVER', 'messaging_service'),

    'drivers' => [
        'messaging_service' => [
            'endpoint' => env('SMS_ENDPOINT', 'https://messaging-service.co.tz/api/sms/v1/test/text/single'),
            'auth_header' => env('SMS_AUTH_HEADER', 'cm9iYmluaG9vZDpUM2NoZ3VydTI0Lg=='),
            'from' => env('SMS_FROM', 'N-SMS'),
            'timeout' => env('SMS_TIMEOUT', 10), // Reduced timeout for faster response
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Settings
    |--------------------------------------------------------------------------
    */

    'enabled' => env('SMS_ENABLED', true),
    'log_requests' => env('SMS_LOG_REQUESTS', true),
];
