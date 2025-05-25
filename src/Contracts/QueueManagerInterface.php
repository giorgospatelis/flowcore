<?php

declare(strict_types=1);

namespace FlowCore\Contracts;

use FlowCore\Core\Job;

interface QueueManagerInterface
{
    /**
     * Push a job onto a queue.
     */
    public function push(string $queue, Job $job): string;

    /**
     * Push a job onto a queue with a delay.
     */
    public function later(string $queue, Job $job, int $delay): string;

    /**
     * Pop a job from a queue.
     */
    public function pop(string $queue, int $timeout = 0): ?Job;

    /**
     * Push multiple jobs onto a queue.
     */
    public function bulk(string $queue, array $jobs): array;

    /**
     * Get the size of a queue.
     */
    public function size(string $queue): int;

    /**
     * Clear all jobs from a queue.
     */
    public function clear(string $queue): void;

    /**
     * Get all available queues.
     */
    public function getQueues(): array;

    /**
     * Acknowledge a job as completed.
     */
    public function ack(string $queue, string $jobId): void;

    /**
     * Reject a job (mark as failed).
     */
    public function nack(string $queue, string $jobId): void;

    /**
     * Release a job back to the queue.
     */
    public function release(string $queue, string $jobId, int $delay = 0): void;

    /**
     * Delete a job from the queue.
     */
    public function delete(string $queue, string $jobId): bool;

    /**
     * Get a specific job by ID.
     */
    public function find(string $queue, string $jobId): ?Job;

    /**
     * Add a new connection.
     */
    public function addConnection(string $name, QueueDriverInterface $driver): void;

    /**
     * Get a connection by name.
     */
    public function connection(string $name = null): QueueDriverInterface;

    /**
     * Set the default connection.
     */
    public function setDefaultConnection(string $name): void;

    /**
     * Get connection info and statistics.
     */
    public function getConnectionInfo(string $name = null): array;
}
