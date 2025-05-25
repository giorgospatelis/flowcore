<?php

namespace FlowCore\Drivers;

use FlowCore\Contracts\JobPayloadInterface;
use FlowCore\Contracts\QueueDriverInterface;
use Predis\Client;

final readonly class RedisDriver implements QueueDriverInterface
{
    public function __construct(private Client $redis)
    {
    }

    public function push(string $queue, JobPayloadInterface $job): void
    {
        $id = $this->generateId();
        $job->setId($id);

        $this->redis->rpush("queue:{$queue}", $job->serialize());
        $this->redis->hset("job:{$id}", 'data', $job->serialize());
        $this->redis->hset("job:{$id}", 'status', 'pending');
    }

    public function pop(string $queue, int $timeout = 0): ?JobPayloadInterface
    {
        $data = $this->redis->lpop("queue:{$queue}");
        if ($data === null) {
            return null;
        }

        // Deserialize the job data
        return JobPayloadInterface::unserialize($data);
    }

    public function ack(string $queue, string $jobId): void
    {
        // Acknowledge the job by removing it from the Redis hash
        $this->redis->hdel("job:{$jobId}", 'data');
    }

    public function nack(string $queue, string $jobId): void
    {
        // No action needed for nack in Redis as jobs are not removed from the queue
    }

    private function generateId(): string
    {
        return uniqid('job_', true);
    }

    public function get(string $queue, string $jobId): ?JobPayloadInterface
    {
        $data = $this->redis->hget("job:{$jobId}", 'data');
        if ($data === null) {
            return null;
        }

        return JobPayloadInterface::unserialize($data);
    }

    public function delete(string $queue, string $jobId): bool
    {
        return $this->redis->del("job:{$jobId}") > 0;
    }

    public function release(string $queue, string $jobId, int $delay = 0): void
    {
        // Releasing a job is not supported in Redis as it does not have a built-in delay mechanism
    }

    public function size(string $queue): int
    {
        return $this->redis->llen("queue:{$queue}");
    }

    public function clear(string $queue): void
    {
        $this->redis->del("queue:{$queue}");
    }

    public function queues(): array
    {
        // This method would require additional logic to list all queues in Redis
        return [];
    }

    public function later(string $queue, JobPayloadInterface $payload, int $delay): void
    {
        // Delayed jobs are not supported in this implementation
    }

    public function scheduled(string $queue, int $timestamp): array
    {
        // Scheduled jobs are not supported in this implementation
        return [];
    }

    public function pushBatch(string $queue, array $payloads): array
    {
        // Batch push is not supported in this implementation
        return [];
    }

    public function popBatch(string $queue, int $count): array
    {
        // Batch pop is not supported in this implementation
        return [];
    }

    public function supportsPriority(): bool
    {
        return false; // Redis does not support priority natively
    }

    public function supportsDelayed(): bool
    {
        return false; // Delayed jobs are not implemented in this driver
    }

    public function supportsTransactions(): bool
    {
        return false; // Redis does not support transactions in the same way as databases do
    }

    public function supportsStreaming(): bool
    {
        return false; // Redis does not support streaming in the context of job processing
    }

    public function supportsBatchOperations(): bool
    {
        return false; // Redis does not support batch operations in this implementation
    }

}
