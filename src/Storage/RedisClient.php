<?php

declare(strict_types=1);

namespace FlowCore\Storage;

use Predis\Client;

final readonly class RedisClient
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'scheme' => 'tcp',
            'host' => $_ENV['FLOWCORE_REDIS_HOST'] ?: '127.0.0.1',
            'port' => $_ENV['FLOWCORE_REDIS_PORT'] ?: 6379,
        ]);
    }

    public function client(): Client
    {
        return $this->client;
    }
}
