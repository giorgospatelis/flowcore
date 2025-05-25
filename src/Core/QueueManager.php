<?php

declare(strict_types=1);

namespace FlowCore\Core;

use FlowCore\Contracts\QueueDriverInterface;
use FlowCore\Contracts\QueueManagerInterface;
use FlowCore\Support\Exceptions\QueueException;
use InvalidArgumentException;
use Throwable;

final class QueueManager implements QueueManagerInterface
{
    private array $connections = [];

    private string $defaultConnection = 'default';

    public function __construct(array $connections = [], string $defaultConnection = 'default')
    {
        foreach ($connections as $name => $driver) {
            $this->addConnection($name, $driver);
        }

        if ($defaultConnection !== '' && $defaultConnection !== '0') {
            $this->setDefaultConnection($defaultConnection);
        }
    }

    public function push(string $queue, Job $job): string
    {
        $this->validateQueue($queue);

        $driver = $this->resolveConnection($job->getQueue() !== 'default' ? null : $this->defaultConnection);
        $payload = $job->toPayload();

        // Generate ID if not set
        if (in_array($payload->getId(), ['', '0'], true)) {
            $payload->setId($this->generateJobId());
            $job->setId($payload->getId());
        }

        try {
            if ($job->getDelay() > 0) {
                $driver->later($queue, $payload, $job->getDelay());
            } else {
                $driver->push($queue, $payload);
            }

            return $payload->getId();
        } catch (Throwable $e) {
            throw new QueueException(
                "Failed to push job to queue '{$queue}': ".$e->getMessage(),
                0,
                $e
            );
        }
    }

    public function later(string $queue, Job $job, int $delay): string
    {
        $this->validateQueue($queue);
        $this->validateDelay($delay);

        $driver = $this->resolveConnection();
        $payload = $job->toPayload();

        // Generate ID if not set
        if (in_array($payload->getId(), ['', '0'], true)) {
            $payload->setId($this->generateJobId());
            $job->setId($payload->getId());
        }

        try {
            $driver->later($queue, $payload, $delay);

            return $payload->getId();
        } catch (Throwable $e) {
            throw new QueueException(
                "Failed to schedule job on queue '{$queue}': ".$e->getMessage(),
                0,
                $e
            );
        }
    }

    public function pop(string $queue, int $timeout = 0): ?Job
    {
        $this->validateQueue($queue);
        $this->validateTimeout($timeout);

        $driver = $this->resolveConnection();

        try {
            $payload = $driver->pop($queue, $timeout);

            if (! $payload instanceof \FlowCore\Contracts\JobPayloadInterface) {
                return null;
            }

            return Job::fromPayload($payload);
        } catch (Throwable $e) {
            throw new QueueException(
                "Failed to pop job from queue '{$queue}': ".$e->getMessage(),
                0,
                $e
            );
        }
    }

    public function bulk(string $queue, array $jobs): array
    {
        $this->validateQueue($queue);

        if ($jobs === []) {
            return [];
        }

        $this->validateJobsArray($jobs);

        $driver = $this->resolveConnection();
        $payloads = [];
        $jobIds = [];

        // Convert jobs to payloads and assign IDs
        foreach ($jobs as $job) {
            $payload = $job->toPayload();

            if (empty($payload->getId())) {
                $jobId = $this->generateJobId();
                $payload->setId($jobId);
                $job->setId($jobId);
            }

            $payloads[] = $payload;
            $jobIds[] = $payload->getId();
        }

        try {
            if ($driver->supportsBatchOperations()) {
                $driver->pushBatch($queue, $payloads);
            } else {
                // Fallback to individual pushes
                foreach ($payloads as $payload) {
                    $driver->push($queue, $payload);
                }
            }

            return $jobIds;
        } catch (Throwable $e) {
            throw new QueueException(
                "Failed to bulk push jobs to queue '{$queue}': ".$e->getMessage(),
                0,
                $e
            );
        }
    }

    public function size(string $queue): int
    {
        $this->validateQueue($queue);

        $driver = $this->resolveConnection();

        try {
            return $driver->size($queue);
        } catch (Throwable $e) {
            throw new QueueException(
                "Failed to get size of queue '{$queue}': ".$e->getMessage(),
                0,
                $e
            );
        }
    }

    public function clear(string $queue): void
    {
        $this->validateQueue($queue);

        $driver = $this->resolveConnection();

        try {
            $driver->clear($queue);
        } catch (Throwable $e) {
            throw new QueueException(
                "Failed to clear queue '{$queue}': ".$e->getMessage(),
                0,
                $e
            );
        }
    }

    public function getQueues(): array
    {
        $driver = $this->resolveConnection();

        try {
            return $driver->queues();
        } catch (Throwable $e) {
            throw new QueueException(
                'Failed to get queues: '.$e->getMessage(),
                0,
                $e
            );
        }
    }

    public function ack(string $queue, string $jobId): void
    {
        $this->validateQueue($queue);
        $this->validateJobId($jobId);

        $driver = $this->resolveConnection();

        try {
            $driver->ack($queue, $jobId);
        } catch (Throwable $e) {
            throw new QueueException(
                "Failed to acknowledge job '{$jobId}' on queue '{$queue}': ".$e->getMessage(),
                0,
                $e
            );
        }
    }

    public function nack(string $queue, string $jobId): void
    {
        $this->validateQueue($queue);
        $this->validateJobId($jobId);

        $driver = $this->resolveConnection();

        try {
            $driver->nack($queue, $jobId);
        } catch (Throwable $e) {
            throw new QueueException(
                "Failed to reject job '{$jobId}' on queue '{$queue}': ".$e->getMessage(),
                0,
                $e
            );
        }
    }

    public function release(string $queue, string $jobId, int $delay = 0): void
    {
        $this->validateQueue($queue);
        $this->validateJobId($jobId);
        $this->validateDelay($delay);

        $driver = $this->resolveConnection();

        try {
            $driver->release($queue, $jobId, $delay);
        } catch (Throwable $e) {
            throw new QueueException(
                "Failed to release job '{$jobId}' on queue '{$queue}': ".$e->getMessage(),
                0,
                $e
            );
        }
    }

    public function delete(string $queue, string $jobId): bool
    {
        $this->validateQueue($queue);
        $this->validateJobId($jobId);

        $driver = $this->resolveConnection();

        try {
            return $driver->delete($queue, $jobId);
        } catch (Throwable $e) {
            throw new QueueException(
                "Failed to delete job '{$jobId}' from queue '{$queue}': ".$e->getMessage(),
                0,
                $e
            );
        }
    }

    public function find(string $queue, string $jobId): ?Job
    {
        $this->validateQueue($queue);
        $this->validateJobId($jobId);

        $driver = $this->resolveConnection();

        try {
            $payload = $driver->get($queue, $jobId);

            if (! $payload instanceof \FlowCore\Contracts\JobPayloadInterface) {
                return null;
            }

            return Job::fromPayload($payload);
        } catch (Throwable $e) {
            throw new QueueException(
                "Failed to find job '{$jobId}' on queue '{$queue}': ".$e->getMessage(),
                0,
                $e
            );
        }
    }

    public function addConnection(string $name, QueueDriverInterface $driver): void
    {
        if ($name === '' || $name === '0') {
            throw new InvalidArgumentException('Connection name cannot be empty');
        }

        $this->connections[$name] = $driver;
    }

    public function connection(?string $name = null): QueueDriverInterface
    {
        return $this->resolveConnection($name);
    }

    public function setDefaultConnection(string $name): void
    {
        if ($name === '' || $name === '0') {
            throw new InvalidArgumentException('Default connection name cannot be empty');
        }

        $this->defaultConnection = $name;
    }

    public function getConnectionInfo(?string $name = null): array
    {
        $connectionName = $name ?? $this->defaultConnection;
        $driver = $this->resolveConnection($connectionName);

        return [
            'name' => $connectionName,
            'driver' => $driver::class,
            'supports_priority' => $driver->supportsPriority(),
            'supports_delayed' => $driver->supportsDelayed(),
            'supports_transactions' => $driver->supportsTransactions(),
            'supports_streaming' => $driver->supportsStreaming(),
        ];
    }

    /**
     * Get multiple queue statistics.
     */
    public function getStats(array $queues = []): array
    {
        if ($queues === []) {
            $queues = $this->getQueues();
        }

        $stats = [];
        foreach ($queues as $queue) {
            try {
                $stats[$queue] = [
                    'size' => $this->size($queue),
                    'connection' => $this->defaultConnection,
                ];
            } catch (Throwable $e) {
                $stats[$queue] = [
                    'size' => null,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $stats;
    }

    /**
     * Check if a connection exists.
     */
    public function hasConnection(string $name): bool
    {
        return isset($this->connections[$name]);
    }

    /**
     * Get all connection names.
     */
    public function getConnectionNames(): array
    {
        return array_keys($this->connections);
    }

    /**
     * Get the default connection name.
     */
    public function getDefaultConnection(): string
    {
        return $this->defaultConnection;
    }

    private function resolveConnection(?string $name = null): QueueDriverInterface
    {
        $connectionName = $name ?? $this->defaultConnection;

        if (! isset($this->connections[$connectionName])) {
            throw new QueueException("Queue connection '{$connectionName}' not found");
        }

        return $this->connections[$connectionName];
    }

    private function generateJobId(): string
    {
        return uniqid('job_', true).'_'.mt_rand(1000, 9999);
    }

    private function validateQueue(string $queue): void
    {
        if ($queue === '' || $queue === '0') {
            throw new InvalidArgumentException('Queue name cannot be empty');
        }
    }

    private function validateJobId(string $jobId): void
    {
        if ($jobId === '' || $jobId === '0') {
            throw new InvalidArgumentException('Job ID cannot be empty');
        }
    }

    private function validateDelay(int $delay): void
    {
        if ($delay < 0) {
            throw new InvalidArgumentException('Delay cannot be negative');
        }
    }

    private function validateTimeout(int $timeout): void
    {
        if ($timeout < 0) {
            throw new InvalidArgumentException('Timeout cannot be negative');
        }
    }

    private function validateJobsArray(array $jobs): void
    {
        foreach ($jobs as $index => $job) {
            if (! $job instanceof Job) {
                throw new InvalidArgumentException(
                    "Job at index {$index} must be an instance of ".Job::class
                );
            }
        }
    }
}
