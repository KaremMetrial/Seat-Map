<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API Rate Limits
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting for API endpoints.
    | Format: "requests,decay_minutes"
    |
    */
    'rate_limits' => [
        'read' => env('API_RATE_READ', '60,1'),      // 60 requests per minute
        'write' => env('API_RATE_WRITE', '10,1'),     // 10 requests per minute
        'bookings' => env('API_RATE_BOOKINGS', '20,1'), // 20 booking requests per minute
    ],

    /*
    |--------------------------------------------------------------------------
    | Socket.IO Server Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the real-time socket server for seat updates.
    |
    */
    'socket' => [
        'enabled' => env('SOCKET_ENABLED', true),
        'host' => env('SOCKET_HOST', '127.0.0.1'),
        'port' => env('SOCKET_PORT', 3001),
    ],
];