<?php

namespace FlowCore\Storage;

use Predis\Client;

class RedisClient
{
    private Client $client;

    public function __construct(array $config = [])
    {
        $this->client = new Client([
            'scheme' => 'tcp',
            'host'   => $_ENV['FLOWCORE_REDIS_HOST'] ?: 'zn',
            'port'   => $_ENV['FLOWCORE_REDIS_PORT'] ?: 6379,
        ]);
    }

    public function client(): Client
    {
        return $this->client;
    }
}
