<?php

declare(strict_types=1);

namespace FlowCore\Drivers;

use DirectoryIterator;
use FlowCore\Contracts\JobPayloadInterface;
use FlowCore\Contracts\QueueDriverInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class FilesystemDriver implements QueueDriverInterface
{
    private readonly string $basePath;

    private array $openFiles = []; // File handle cache

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, DIRECTORY_SEPARATOR);

        $this->ensureDirectoryExists($this->basePath);
    }

    public function __destruct()
    {
        // Clean up open file handles
        foreach ($this->openFiles as $handle) {
            if (is_resource($handle)) {
                flock($handle, LOCK_UN);
                fclose($handle);
            }
        }
    }

    public function push(string $queue, JobPayloadInterface $job): void
    {
        $id = $this->generateId();
        $job->setId($id);

        $queuePath = $this->getQueuePath($queue);
        $this->ensureDirectoryExists($queuePath);

        // Prepare job data
        $jobData = [
            'id' => $id,
            'queue' => $queue,
            'payload' => $job->serialize(),
            'attempts' => 0,
            'created_at' => time(),
            'available_at' => time(),
            'status' => 'pending',
        ];

        // Write job file
        $jobPath = $this->getJobPath($queue, $id);
        $this->writeFileAtomic($jobPath, json_encode($jobData));

        // Add to queue index with locking
        $this->addToQueueIndex($queue, $id, time());
    }

    public function pop(string $queue, int $timeout = 0): ?JobPayloadInterface
    {
        $endTime = time() + $timeout;

        do {
            $jobId = $this->getNextJobId($queue);

            if ($jobId !== null) {
                $jobPath = $this->getJobPath($queue, $jobId);

                // Try to acquire exclusive lock on job file
                $handle = @fopen($jobPath, 'r+');
                if ($handle && flock($handle, LOCK_EX | LOCK_NB)) {
                    $jobData = json_decode(fread($handle, filesize($jobPath)), true);

                    if ($jobData && $jobData['status'] === 'pending' && $jobData['available_at'] <= time()) {
                        // Update job status
                        $jobData['status'] = 'processing';
                        $jobData['started_at'] = time();
                        $jobData['attempts']++;

                        // Write updated data
                        ftruncate($handle, 0);
                        rewind($handle);
                        fwrite($handle, json_encode($jobData));
                        fflush($handle);

                        // Remove from pending index
                        $this->removeFromQueueIndex($queue, $jobId);

                        // Add to processing index
                        $this->addToProcessingIndex($queue, $jobId);

                        flock($handle, LOCK_UN);
                        fclose($handle);

                        return JobPayloadInterface::unserialize($jobData['payload']);
                    }

                    flock($handle, LOCK_UN);
                    fclose($handle);
                }
            }

            if ($timeout > 0 && time() < $endTime) {
                usleep(100000); // 100ms
            }
        } while ($timeout > 0 && time() < $endTime);

        return null;
    }

    public function ack(string $queue, string $jobId): void
    {
        $jobPath = $this->getJobPath($queue, $jobId);

        $handle = @fopen($jobPath, 'r+');
        if ($handle && flock($handle, LOCK_EX)) {
            $jobData = json_decode(fread($handle, filesize($jobPath)), true);

            if ($jobData) {
                $jobData['status'] = 'completed';
                $jobData['completed_at'] = time();

                ftruncate($handle, 0);
                rewind($handle);
                fwrite($handle, json_encode($jobData));
                fflush($handle);
            }

            flock($handle, LOCK_UN);
            fclose($handle);

            // Remove from processing index
            $this->removeFromProcessingIndex($queue, $jobId);

            // Move to completed directory
            $completedPath = $this->getCompletedPath($queue, $jobId);
            $this->ensureDirectoryExists(dirname($completedPath));
            rename($jobPath, $completedPath);
        }
    }

    public function nack(string $queue, string $jobId): void
    {
        $jobPath = $this->getJobPath($queue, $jobId);

        $handle = @fopen($jobPath, 'r+');
        if ($handle && flock($handle, LOCK_EX)) {
            $jobData = json_decode(fread($handle, filesize($jobPath)), true);

            if ($jobData) {
                $jobData['status'] = 'failed';
                $jobData['failed_at'] = time();

                ftruncate($handle, 0);
                rewind($handle);
                fwrite($handle, json_encode($jobData));
                fflush($handle);
            }

            flock($handle, LOCK_UN);
            fclose($handle);

            // Remove from processing index
            $this->removeFromProcessingIndex($queue, $jobId);

            // Move to failed directory
            $failedPath = $this->getFailedPath($queue, $jobId);
            $this->ensureDirectoryExists(dirname($failedPath));
            rename($jobPath, $failedPath);
        }
    }

    public function get(string $queue, string $jobId): ?JobPayloadInterface
    {
        $paths = [
            $this->getJobPath($queue, $jobId),
            $this->getCompletedPath($queue, $jobId),
            $this->getFailedPath($queue, $jobId),
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                $jobData = json_decode(file_get_contents($path), true);
                if ($jobData) {
                    return JobPayloadInterface::unserialize($jobData['payload']);
                }
            }
        }

        return null;
    }

    public function delete(string $queue, string $jobId): bool
    {
        $paths = [
            $this->getJobPath($queue, $jobId),
            $this->getCompletedPath($queue, $jobId),
            $this->getFailedPath($queue, $jobId),
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                // Remove from any indexes
                $this->removeFromQueueIndex($queue, $jobId);
                $this->removeFromProcessingIndex($queue, $jobId);

                return unlink($path);
            }
        }

        return false;
    }

    public function release(string $queue, string $jobId, int $delay = 0): void
    {
        $jobPath = $this->getJobPath($queue, $jobId);

        $handle = @fopen($jobPath, 'r+');
        if ($handle && flock($handle, LOCK_EX)) {
            $jobData = json_decode(fread($handle, filesize($jobPath)), true);

            if ($jobData) {
                $jobData['status'] = 'pending';
                $jobData['available_at'] = time() + $delay;
                unset($jobData['started_at']);

                ftruncate($handle, 0);
                rewind($handle);
                fwrite($handle, json_encode($jobData));
                fflush($handle);
            }

            flock($handle, LOCK_UN);
            fclose($handle);

            // Remove from processing and add back to queue
            $this->removeFromProcessingIndex($queue, $jobId);
            $this->addToQueueIndex($queue, $jobId, time() + $delay);
        }
    }

    public function size(string $queue): int
    {
        $indexPath = $this->getQueueIndexPath($queue);

        if (! file_exists($indexPath)) {
            return 0;
        }

        $handle = fopen($indexPath, 'r');
        if ($handle && flock($handle, LOCK_SH)) {
            $content = fread($handle, filesize($indexPath) ?: 1);
            $index = json_decode($content, true) ?: [];
            flock($handle, LOCK_UN);
            fclose($handle);

            return count($index);
        }

        return 0;
    }

    public function clear(string $queue): void
    {
        $queuePath = $this->getQueuePath($queue);

        if (is_dir($queuePath)) {
            // Clear all subdirectories
            $this->deleteDirectory($queuePath.'/jobs');
            $this->deleteDirectory($queuePath.'/completed');
            $this->deleteDirectory($queuePath.'/failed');

            // Clear indexes
            $indexes = ['index.json', 'processing.json', 'delayed.json'];
            foreach ($indexes as $index) {
                $indexPath = $queuePath.'/'.$index;
                if (file_exists($indexPath)) {
                    unlink($indexPath);
                }
            }
        }
    }

    public function queues(): array
    {
        $queues = [];

        if (is_dir($this->basePath)) {
            $iterator = new DirectoryIterator($this->basePath);
            foreach ($iterator as $item) {
                if ($item->isDir() && ! $item->isDot()) {
                    $queues[] = $item->getFilename();
                }
            }
        }

        return $queues;
    }

    public function later(string $queue, JobPayloadInterface $payload, int $delay): void
    {
        $id = $this->generateId();
        $payload->setId($id);

        $queuePath = $this->getQueuePath($queue);
        $this->ensureDirectoryExists($queuePath);

        // Prepare job data with delayed availability
        $jobData = [
            'id' => $id,
            'queue' => $queue,
            'payload' => $payload->serialize(),
            'attempts' => 0,
            'created_at' => time(),
            'available_at' => time() + $delay,
            'status' => 'pending',
        ];

        // Write job file
        $jobPath = $this->getJobPath($queue, $id);
        $this->writeFileAtomic($jobPath, json_encode($jobData));

        // Add to delayed index
        $this->addToDelayedIndex($queue, $id, time() + $delay);
    }

    public function scheduled(string $queue, int $timestamp): array
    {
        $this->processDelayedJobs($queue);

        $delayedPath = $this->getDelayedIndexPath($queue);
        $scheduled = [];

        if (file_exists($delayedPath)) {
            $handle = fopen($delayedPath, 'r');
            if ($handle && flock($handle, LOCK_SH)) {
                $content = fread($handle, filesize($delayedPath) ?: 1);
                $delayed = json_decode($content, true) ?: [];
                flock($handle, LOCK_UN);
                fclose($handle);

                foreach ($delayed as $jobId => $availableAt) {
                    if ($availableAt <= $timestamp) {
                        $scheduled[] = $jobId;
                    }
                }
            }
        }

        return $scheduled;
    }

    public function pushBatch(string $queue, array $payloads): array
    {
        $ids = [];

        foreach ($payloads as $payload) {
            $ids[] = $this->push($queue, $payload);
        }

        return $ids;
    }

    public function popBatch(string $queue, int $count): array
    {
        $jobs = [];

        for ($i = 0; $i < $count; $i++) {
            $job = $this->pop($queue, 0);
            if (! $job instanceof JobPayloadInterface) {
                break;
            }
            $jobs[] = $job;
        }

        return $jobs;
    }

    public function supportsPriority(): bool
    {
        return false; // Filesystem doesn't efficiently support priority queues
    }

    public function supportsDelayed(): bool
    {
        return true;
    }

    public function supportsTransactions(): bool
    {
        return false;
    }

    public function supportsStreaming(): bool
    {
        return false;
    }

    public function supportsBatchOperations(): bool
    {
        return true; // Batch operations are supported
    }

    // Helper Methods

    private function generateId(): string
    {
        return uniqid('job_', true).'_'.mt_rand(1000, 9999);
    }

    private function getQueuePath(string $queue): string
    {
        return $this->basePath.DIRECTORY_SEPARATOR.$queue;
    }

    private function getJobPath(string $queue, string $jobId): string
    {
        return $this->getQueuePath($queue).'/jobs/'.$jobId.'.json';
    }

    private function getCompletedPath(string $queue, string $jobId): string
    {
        return $this->getQueuePath($queue).'/completed/'.$jobId.'.json';
    }

    private function getFailedPath(string $queue, string $jobId): string
    {
        return $this->getQueuePath($queue).'/failed/'.$jobId.'.json';
    }

    private function getQueueIndexPath(string $queue): string
    {
        return $this->getQueuePath($queue).'/index.json';
    }

    private function getProcessingIndexPath(string $queue): string
    {
        return $this->getQueuePath($queue).'/processing.json';
    }

    private function getDelayedIndexPath(string $queue): string
    {
        return $this->getQueuePath($queue).'/delayed.json';
    }

    private function ensureDirectoryExists(string $path): void
    {
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    private function writeFileAtomic(string $path, string $content): void
    {
        $dir = dirname($path);
        $this->ensureDirectoryExists($dir);

        $tempPath = $path.'.tmp.'.uniqid();

        if (file_put_contents($tempPath, $content, LOCK_EX) !== false) {
            rename($tempPath, $path);
        } else {
            throw new RuntimeException("Failed to write file: {$path}");
        }
    }

    private function addToQueueIndex(string $queue, string $jobId, int $availableAt): void
    {
        $this->modifyIndex($this->getQueueIndexPath($queue), function (array $index) use ($jobId, $availableAt): array {
            $index[$jobId] = $availableAt;
            asort($index); // Sort by available time

            return $index;
        });
    }

    private function removeFromQueueIndex(string $queue, string $jobId): void
    {
        $this->modifyIndex($this->getQueueIndexPath($queue), function (array $index) use ($jobId): array {
            unset($index[$jobId]);

            return $index;
        });
    }

    private function addToProcessingIndex(string $queue, string $jobId): void
    {
        $this->modifyIndex($this->getProcessingIndexPath($queue), function (array $index) use ($jobId) {
            $index[$jobId] = time();

            return $index;
        });
    }

    private function removeFromProcessingIndex(string $queue, string $jobId): void
    {
        $this->modifyIndex($this->getProcessingIndexPath($queue), function (array $index) use ($jobId): array {
            unset($index[$jobId]);

            return $index;
        });
    }

    private function addToDelayedIndex(string $queue, string $jobId, int $availableAt): void
    {
        $this->modifyIndex($this->getDelayedIndexPath($queue), function (array $index) use ($jobId, $availableAt): array {
            $index[$jobId] = $availableAt;
            asort($index);

            return $index;
        });
    }

    private function modifyIndex(string $indexPath, callable $modifier): void
    {
        $dir = dirname($indexPath);
        $this->ensureDirectoryExists($dir);

        $handle = fopen($indexPath, 'c+');
        if (! $handle) {
            throw new RuntimeException("Failed to open index file: {$indexPath}");
        }

        $acquired = flock($handle, LOCK_EX);
        if (! $acquired) {
            fclose($handle);
            throw new RuntimeException("Failed to acquire lock on index: {$indexPath}");
        }

        try {
            // Read current index
            $size = filesize($indexPath);
            $content = $size > 0 ? fread($handle, $size) : '';
            $index = $content ? json_decode($content, true) : [];

            // Modify index
            $index = $modifier($index ?: []);

            // Write back
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($index));
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function getNextJobId(string $queue): ?string
    {
        // First, process any delayed jobs that are now ready
        $this->processDelayedJobs($queue);

        $indexPath = $this->getQueueIndexPath($queue);

        if (! file_exists($indexPath)) {
            return null;
        }

        $handle = fopen($indexPath, 'r');
        if ($handle && flock($handle, LOCK_SH)) {
            $content = fread($handle, filesize($indexPath) ?: 1);
            $index = json_decode($content, true) ?: [];
            flock($handle, LOCK_UN);
            fclose($handle);

            $now = time();
            foreach ($index as $jobId => $availableAt) {
                if ($availableAt <= $now) {
                    return $jobId;
                }
            }
        }

        return null;
    }

    private function processDelayedJobs(string $queue): void
    {
        $delayedPath = $this->getDelayedIndexPath($queue);

        if (! file_exists($delayedPath)) {
            return;
        }

        $this->modifyIndex($delayedPath, function ($delayed) use ($queue): array {
            $now = time();
            $ready = [];
            $remaining = [];

            foreach ($delayed as $jobId => $availableAt) {
                if ($availableAt <= $now) {
                    $ready[$jobId] = $availableAt;
                } else {
                    $remaining[$jobId] = $availableAt;
                }
            }

            // Move ready jobs to main queue
            foreach ($ready as $jobId => $availableAt) {
                $this->addToQueueIndex($queue, $jobId, $availableAt);
            }

            return $remaining;
        });
    }

    private function deleteDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($path);
    }
}
