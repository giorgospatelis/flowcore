<?php

declare(strict_types=1);

namespace FlowCore\Queue;

use FlowCore\Storage\RedisClient;

final class RedisQueue implements QueueInterface
{
    private RedisClient $redisClient;

    /**
     * @var string The key used in Redis to store the queue.
     */
    private string $queueKey = 'flowcore:queue';

    public function __construct(RedisClient $redis)
    {
        $this->redisClient = $redis;
    }

    /**
     * Enqueue a job into the Redis queue.
     *
     * @param  array<string, mixed>  $job  The job to enqueue, typically containing 'name' and 'payload'.
     */
    public function enqueue(array $job): void
    {
        $this->redisClient->client()->rpush($this->queueKey, [json_encode($job)]);
    }

    /**
     * Dequeue a job from the Redis queue.
     *
     * @return array<string, mixed>|null The dequeued job, or null if the queue is empty.
     */
    public function dequeue(): ?array
    {
        $data = $this->redisClient->client()->lpop($this->queueKey);
        if ($data === null) {
            return null;
        }
        $decoded = json_decode($data, true);
        if (! is_array($decoded)) {
            return null;
        }
        // Ensure all keys are strings
        foreach (array_keys($decoded) as $key) {
            if (! is_string($key)) {
                return null;
            }
        }

        return $decoded;
    }
}
