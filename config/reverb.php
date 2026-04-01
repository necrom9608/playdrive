<?php

$defaultHost = parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: '127.0.0.1';
$defaultScheme = env('REVERB_SCHEME', env('VITE_REVERB_SCHEME', 'http'));

return [
    'default' => env('REVERB_SERVER', 'reverb'),

    'servers' => [
        'reverb' => [
            'host' => env('REVERB_SERVER_HOST', '0.0.0.0'),
            'port' => (int) env('REVERB_SERVER_PORT', 8080),
            'hostname' => env('REVERB_HOST', env('VITE_REVERB_HOST', $defaultHost)),
            'options' => [],
            'max_request_size' => (int) env('REVERB_MAX_REQUEST_SIZE', 10000),
            'scaling' => [
                'enabled' => false,
                'channel' => env('REVERB_SCALING_CHANNEL', 'reverb'),
            ],
            'pulse_ingest_interval' => 15,
            'telescope_ingest_interval' => 15,
        ],
    ],

    'apps' => [
        'provider' => 'config',

        'apps' => [
            [
                'app_id' => env('REVERB_APP_ID', '1'),
                'key' => env('REVERB_APP_KEY', 'playdrive'),
                'secret' => env('REVERB_APP_SECRET', 'playdrive-secret'),
                'options' => [
                    'host' => env('REVERB_HOST', '127.0.0.1'),
                    'port' => (int) env('REVERB_PORT', 9090),
                    'scheme' => env('REVERB_SCHEME', 'http'),
                    'useTLS' => env('REVERB_SCHEME', 'http') === 'https',
                ],
                'allowed_origins' => ['*'],
                'ping_interval' => 60,
                'activity_timeout' => 30,
                'max_message_size' => 10000,
            ],
        ],
    ],
];
