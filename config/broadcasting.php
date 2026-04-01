<?php

$defaultHost = parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: '127.0.0.1';
$defaultScheme = env('REVERB_SCHEME', env('VITE_REVERB_SCHEME', 'http'));

return [
    'default' => env('BROADCAST_CONNECTION', 'null'),

    'connections' => [
        'reverb' => [
            'driver' => 'reverb',
            'key' => env('REVERB_APP_KEY', env('VITE_REVERB_APP_KEY', 'playdrive')),
            'secret' => env('REVERB_APP_SECRET', 'playdrive-secret'),
            'app_id' => env('REVERB_APP_ID', 'playdrive'),
            'options' => [
                'host' => env('REVERB_HOST', env('VITE_REVERB_HOST', $defaultHost)),
                'port' => (int) env('REVERB_PORT', env('VITE_REVERB_PORT', 8080)),
                'scheme' => $defaultScheme,
                'useTLS' => $defaultScheme === 'https',
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
