<?php

declare(strict_types=1);

namespace FlowCore\Drivers;

use FlowCore\Contracts\JobPayloadInterface;
use FlowCore\Contracts\QueueDriverInterface;

final class InMemoryDriver implements QueueDriverInterface
{
    private array $queues = [];

    private array $delayed = [];

    private array $jobs = [];

    private int $idCounter = 1;

    public function push(string $queue, JobPayloadInterface $job): void
    {
        $id = $this->generateId();
        $job->setId($id);

        $this->jobs[$id] = $job;
        $this->queues[$queue][] = $id;
    }

    public function pop(string $queue, int $timeout = 0): ?JobPayloadInterface
    {
        $this->processDelayed($queue);

        if (empty($this->queues[$queue])) {
            if ($timeout > 0) {
                // Simulate blocking with sleep
                sleep(min($timeout, 1));

                return $this->pop($queue, 0);
            }

            return null;
        }

        $jobId = array_shift($this->queues[$queue]);

        return $this->jobs[$jobId] ?? null;
    }

    public function ack(string $queue, string $jobId): void
    {
        if (isset($this->jobs[$jobId])) {
            unset($this->jobs[$jobId]);
        }
    }

    public function nack(string $queue, string $jobId): void
    {
        // In an in-memory driver, we simply do nothing on nack
        // as jobs are not persisted.
    }

    public function get(string $queue, string $jobId): ?JobPayloadInterface
    {
        return $this->jobs[$jobId] ?? null;
    }

    public function delete(string $queue, string $jobId): bool
    {
        if (isset($this->jobs[$jobId])) {
            unset($this->jobs[$jobId]);

            return true;
        }

        return false;
    }

    public function release(string $queue, string $jobId, int $delay = 0): void
    {
        if (isset($this->jobs[$jobId])) {
            $payload = $this->jobs[$jobId];
            unset($this->jobs[$jobId]);
            $this->later($queue, $payload, $delay);
        }
    }

    public function size(string $queue): int
    {
        $this->processDelayed($queue);

        return count($this->queues[$queue] ?? []);
    }

    public function clear(string $queue): void
    {
        unset($this->queues[$queue]);
        unset($this->delayed[$queue]);
    }

    public function queues(): array
    {
        return array_keys($this->queues);
    }

    public function scheduled(string $queue, int $timestamp): array
    {
        $this->processDelayed($queue);

        return array_filter($this->delayed[$queue] ?? [], fn (array $job): bool => $job['available_at'] <= $timestamp);
    }

    public function pushBatch(string $queue, array $payloads): array
    {
        $ids = [];
        foreach ($payloads as $payload) {
            $id = $this->generateId();
            $payload->setId($id);
            $this->jobs[$id] = $payload;
            $this->queues[$queue][] = $id;
            $ids[] = $id;
        }

        return $ids;
    }

    public function popBatch(string $queue, int $count): array
    {
        $this->processDelayed($queue);

        if (empty($this->queues[$queue])) {
            return [];
        }

        $jobIds = array_splice($this->queues[$queue], 0, $count);

        return array_map(fn ($id) => $this->jobs[$id] ?? null, $jobIds);
    }

    public function supportsPriority(): bool
    {
        return false; // In-memory driver does not support priority
    }

    public function supportsDelayed(): bool
    {
        return true; // In-memory driver supports delayed jobs
    }

    public function supportsTransactions(): bool
    {
        return false; // In-memory driver does not support transactions
    }

    public function supportsStreaming(): bool
    {
        return false; // In-memory driver does not support streaming
    }

    public function supportsBatchOperations(): bool
    {
        return true; // In-memory driver supports batch operations
    }

    public function later(string $queue, JobPayloadInterface $payload, int $delay): void
    {
        $id = $this->generateId();
        $payload->setId($id);

        $this->jobs[$id] = $payload;
        $this->delayed[$queue][] = [
            'id' => $id,
            'available_at' => time() + $delay,
        ];
    }

    private function generateId(): string
    {
        return 'job-'.$this->idCounter++;
    }

    private function processDelayed(string $queue): void
    {
        if (! isset($this->delayed[$queue])) {
            return;
        }

        $now = time();
        $ready = [];
        $remaining = [];

        foreach ($this->delayed[$queue] as $job) {
            if ($job['available_at'] <= $now) {
                $ready[] = $job['id'];
            } else {
                $remaining[] = $job;
            }
        }

        $this->delayed[$queue] = $remaining;
        foreach ($ready as $jobId) {
            $this->queues[$queue][] = $jobId;
        }
    }
}
