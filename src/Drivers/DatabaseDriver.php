<?php

declare(strict_types=1);

namespace FlowCore\Drivers;

use Exception;
use FlowCore\Contracts\JobPayloadInterface;
use FlowCore\Contracts\QueueDriverInterface;
use PDO;

final readonly class DatabaseDriver implements QueueDriverInterface
{
    private string $table;

    public function __construct(private PDO $pdo, array $config = [])
    {
        $this->table = $config['table'] ?? 'queue_jobs';
        $this->ensureTableExists();
    }

    public function push(string $queue, JobPayloadInterface $job): void
    {
        $id = $this->generateId();
        $job->setId($id);

        $stmt = $this->pdo->prepare(
            "INSERT INTO {$this->table}
            (id, queue, payload, status, priority, attempts, created_at, available_at)
            VALUES (?, ?, ?, 'pending', ?, 0, ?, ?)
            "
        );
        $stmt->execute([
            $id,
            $queue,
            $job->serialize(),
            $job->getOptions()['priority'] ?? 0,
            time(),
            time(),
        ]);
    }

    public function pop(string $queue, int $timeout = 0): ?JobPayloadInterface
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE queue = :queue AND status = 'pending' LIMIT 1");
        $stmt->execute([':queue' => $queue]);
        $jobData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (! $jobData) {
            return null;
        }

        // Update job status to 'processing'
        $this->ack($queue, $jobData['job_id']);

        return JobPayloadInterface::unserialize($jobData['data']);
    }

    public function ack(string $queue, string $jobId): void
    {
        $stmt = $this->pdo->prepare("UPDATE {$this->table} SET status = 'completed' WHERE queue = :queue AND job_id = :job_id");
        $stmt->execute([':queue' => $queue, ':job_id' => $jobId]);
    }

    public function nack(string $queue, string $jobId): void
    {
        $stmt = $this->pdo->prepare("UPDATE {$this->table} SET status = 'failed' WHERE queue = :queue AND job_id = :job_id");
        $stmt->execute([':queue' => $queue, ':job_id' => $jobId]);
    }

    public function get(string $queue, string $jobId): ?JobPayloadInterface
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE queue = :queue AND job_id = :job_id");
        $stmt->execute([':queue' => $queue, ':job_id' => $jobId]);
        $jobData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (! $jobData) {
            return null;
        }

        return JobPayloadInterface::unserialize($jobData['data']);
    }

    public function delete(string $queue, string $jobId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE queue = :queue AND job_id = :job_id");

        return $stmt->execute([':queue' => $queue, ':job_id' => $jobId]);
    }

    public function release(string $queue, string $jobId, int $delay = 0): void
    {
        // This method can be implemented to handle job re-queuing with delay
        // For simplicity, we will just nack the job
        $this->nack($queue, $jobId);
    }

    public function size(string $queue): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$this->table} WHERE queue = :queue AND status = 'pending'");
        $stmt->execute([':queue' => $queue]);

        return (int) $stmt->fetchColumn();
    }

    public function clear(string $queue): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE queue = :queue");
        $stmt->execute([':queue' => $queue]);
    }

    public function queues(): array
    {
        $stmt = $this->pdo->query("SELECT DISTINCT queue FROM {$this->table}");

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function later(string $queue, JobPayloadInterface $payload, int $delay): void
    {
        $id = $this->generateId();
        $payload->setId($id);

        $stmt = $this->pdo->prepare(
            "INSERT INTO {$this->table}
            (id, queue, payload, status, priority, attempts, created_at, available_at)
            VALUES (?, ?, ?, 'pending', ?, 0, ?, ?)"
        );

        $stmt->execute([
            $id,
            $queue,
            $payload->serialize(),
            $payload->getOptions()['priority'] ?? 0,
            time(),
            time() + $delay,
        ]);
    }

    public function scheduled(string $queue, int $timestamp): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE queue = :queue AND scheduled_at <= :timestamp");
        $stmt->execute([':queue' => $queue, ':timestamp' => $timestamp]);
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn (array $jobData): JobPayloadInterface => JobPayloadInterface::unserialize($jobData['data']), $jobs);
    }

    public function pushBatch(string $queue, array $payloads): array
    {
        $ids = [];
        foreach ($payloads as $payload) {
            $this->push($queue, $payload);
            $ids[] = $payload->getId();
        }

        return $ids;
    }

    public function popBatch(string $queue, int $count): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE queue = :queue AND status = 'pending' LIMIT :count");
        $stmt->bindValue(':queue', $queue);
        $stmt->bindValue(':count', $count, PDO::PARAM_INT);
        $stmt->execute();
        $jobsData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (! $jobsData) {
            return [];
        }

        // Update job statuses to 'processing'
        foreach ($jobsData as $jobData) {
            $this->ack($queue, $jobData['job_id']);
        }

        return array_map(fn (array $jobData): JobPayloadInterface => JobPayloadInterface::unserialize($jobData['data']), $jobsData);
    }

    public function supportsPriority(): bool
    {
        return false; // Database driver does not support priority
    }

    public function supportsDelayed(): bool
    {
        return true; // Database driver supports delayed jobs
    }

    public function supportsTransactions(): bool
    {
        return true; // Database driver supports transactions
    }

    public function supportsStreaming(): bool
    {
        return false; // Database driver does not support streaming
    }

    public function supportsBatchOperations(): bool
    {
        return true; // Database driver supports batch operations
    }

    private function generateId(): string
    {
        return uniqid('job-', true);
    }

    private function ensureTableExists(): void
    {
        $sql = match ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)) {
            'mysql' => "
                CREATE TABLE IF NOT EXISTS {$this->table} (
                    id VARCHAR(255) PRIMARY KEY,
                    queue VARCHAR(255) NOT NULL,
                    payload LONGTEXT NOT NULL,
                    status VARCHAR(50) NOT NULL,
                    priority INT DEFAULT 0,
                    attempts INT DEFAULT 0,
                    created_at INT NOT NULL,
                    available_at INT NOT NULL,
                    started_at INT NULL,
                    completed_at INT NULL,
                    INDEX queue_status_available (queue, status, available_at),
                    INDEX queue_priority (queue, priority DESC)
                ) ENGINE=InnoDB
            ",
            'pgsql' => "
                CREATE TABLE IF NOT EXISTS {$this->table} (
                    id VARCHAR(255) PRIMARY KEY,
                    queue VARCHAR(255) NOT NULL,
                    payload TEXT NOT NULL,
                    status VARCHAR(50) NOT NULL,
                    priority INT DEFAULT 0,
                    attempts INT DEFAULT 0,
                    created_at INT NOT NULL,
                    available_at INT NOT NULL,
                    started_at INT NULL,
                    completed_at INT NULL
                );
                CREATE INDEX IF NOT EXISTS idx_queue_status_available
                ON {$this->table} (queue, status, available_at);
                CREATE INDEX IF NOT EXISTS idx_queue_priority
                ON {$this->table} (queue, priority DESC);
            ",
            'sqlite' => "
                CREATE TABLE IF NOT EXISTS {$this->table} (
                    id TEXT PRIMARY KEY,
                    queue TEXT NOT NULL,
                    payload TEXT NOT NULL,
                    status TEXT NOT NULL,
                    priority INTEGER DEFAULT 0,
                    attempts INTEGER DEFAULT 0,
                    created_at INTEGER NOT NULL,
                    available_at INTEGER NOT NULL,
                    started_at INTEGER NULL,
                    completed_at INTEGER NULL
                );
                CREATE INDEX IF NOT EXISTS idx_queue_status_available
                ON {$this->table} (queue, status, available_at);
                CREATE INDEX IF NOT EXISTS idx_queue_priority
                ON {$this->table} (queue, priority DESC);
            ",
            default => throw new Exception('Unsupported database driver')
        };

        $this->pdo->exec($sql);
    }
}
