<?php

namespace FlowCore\Storage;

use Predis\Client;

class RedisClient
{
    private Client $client;

    public function __construct(array $config = [])
    {
        $this->client = new Client($config);
    }

    public function client(): Client
    {
        return $this->client;
    }
}
