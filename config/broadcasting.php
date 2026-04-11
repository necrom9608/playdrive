<?php

$defaultHost = parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: '127.0.0.1';
$defaultScheme = env('VITE_REVERB_SCHEME', env('REVERB_SCHEME', parse_url(env('APP_URL', 'http://localhost'), PHP_URL_SCHEME) ?: 'http'));
$defaultPort = (int) env('VITE_REVERB_PORT', env('REVERB_PORT', $defaultScheme === 'https' ? 443 : 80));

return [
    'default' => env('BROADCAST_CONNECTION', 'null'),


    'realtime' => [
        'app_key' => env('VITE_REVERB_APP_KEY', env('REVERB_APP_KEY', 'playdrive')),
        'host' => env('VITE_REVERB_HOST', env('REVERB_HOST', $defaultHost)),
        'port' => $defaultPort,
        'scheme' => $defaultScheme,
    ],

    'connections' => [
        'reverb' => [
            'driver' => 'reverb',
            'key' => env('REVERB_APP_KEY', env('VITE_REVERB_APP_KEY', 'playdrive')),
            'secret' => env('REVERB_APP_SECRET', 'playdrive-secret'),
            'app_id' => env('REVERB_APP_ID', 'playdrive'),
            'options' => [
                'host' => env('REVERB_HOST', env('VITE_REVERB_HOST', $defaultHost)),
                'port' => $defaultPort,
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
