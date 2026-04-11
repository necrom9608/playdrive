<?php

$defaultHost = parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: '127.0.0.1';

return [
    'default' => env('BROADCAST_CONNECTION', 'null'),

    'realtime' => [
        'app_key' => env('VITE_REVERB_APP_KEY', 'playdrive'),
        'host' => env('VITE_REVERB_HOST', $defaultHost),
        'port' => (int) env('VITE_REVERB_PORT', 443),
        'scheme' => env('VITE_REVERB_SCHEME', 'https'),
    ],

    'connections' => [
        'reverb' => [
            'driver' => 'reverb',
            'key' => env('REVERB_APP_KEY', 'playdrive'),
            'secret' => env('REVERB_APP_SECRET', 'playdrive-secret'),
            'app_id' => env('REVERB_APP_ID', 'playdrive'),
            'options' => [
                'host' => env('REVERB_HOST', '127.0.0.1'),
                'port' => (int) env('REVERB_PORT', 8080),
                'scheme' => env('REVERB_SCHEME', 'http'),
                'useTLS' => env('REVERB_SCHEME', 'http') === 'https',
            ],
            'client_options' => [],
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],
    ],
];
