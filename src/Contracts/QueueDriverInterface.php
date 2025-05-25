<?php

declare(strict_types=1);

namespace FlowCore\Contracts;

interface QueueDriverInterface
{
    /**
     * Core Operations for a queue driver.
     */
    public function push(string $queue, JobPayloadInterface $job): void;

    public function pop(string $queue, int $timeout = 0): ?JobPayloadInterface;

    public function ack(string $queue, string $jobId): void;

    public function nack(string $queue, string $jobId): void;

    /**
     * Job Management Operations.
     */
    public function get(string $queue, string $jobId): ?JobPayloadInterface;

    public function delete(string $queue, string $jobId): bool;

    public function release(string $queue, string $jobId, int $delay = 0): void;

    /**
     * Queue Management Operations.
     */
    public function size(string $queue): int;

    public function clear(string $queue): void;

    public function queues(): array;

    /**
     * Delayed Job Operations.
     */
    public function later(string $queue, JobPayloadInterface $payload, int $delay): void;

    public function scheduled(string $queue, int $timestamp): array;

    /**
     * Batch Operations.
     */
    public function pushBatch(string $queue, array $payloads): array;

    public function popBatch(string $queue, int $count): array;

    /**
     * Advanced Features.
     */
    public function supportsPriority(): bool;

    public function supportsDelayed(): bool;

    public function supportsTransactions(): bool;

    public function supportsStreaming(): bool;

    public function supportsBatchOperations(): bool;
}
