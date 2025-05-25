<?php

declare(strict_types=1);

return [
    'default' => $_ENV['FLOWCORE_QUEUE_DRIVER'] ?? 'redis',

    'connections' => [
        'redis' => [
            'driver' => 'redis',
            'host' => $_ENV['FLOWCORE_REDIS_HOST'] ?? '127.0.0.1',
            'port' => $_ENV['FLOWCORE_REDIS_PORT'] ?? 6379,
            'database' => $_ENV['FLOWCORE_REDIS_DB'] ?? 0,
            'password' => $_ENV['FLOWCORE_REDIS_PASSWORD'] ?? null,
        ],

        'database' => [
            'driver' => 'database',
            'connection' => $_ENV['DB_CONNECTION'] ?? 'mysql',
            'table' => 'queue_jobs',
        ],

        'memory' => [
            'driver' => 'memory',
        ],

        'filesystem' => [
            'driver' => 'filesystem',
            'path' => $_ENV['FLOWCORE_QUEUE_PATH'] ?? '/tmp/flowcore',
        ],
    ],
];
