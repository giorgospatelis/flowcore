<?php

declare(strict_types=1);

namespace FlowCore\Queue;

use FlowCore\Storage\RedisClient;

final class RedisQueue implements QueueInterface
{
    private string $queueKey = 'flowcore:queue';

    public function __construct(private readonly RedisClient $redis) {}

    public function enqueue(array $job): void
    {
        $this->redis->client()->rpush($this->queueKey, [json_encode($job)]);
    }

    public function dequeue(): ?array
    {
        $job = $this->redis->client()->lpop($this->queueKey);

        return $job ? json_decode($job, true) : null;
    }
}
