<?php

declare(strict_types=1);

namespace FlowCore\Core;

use FlowCore\Contracts\JobPayloadInterface;
use InvalidArgumentException;
use JsonSerializable;
use ReflectionClass;
use ReflectionProperty;
use Throwable;

abstract class Job implements JsonSerializable
{
    protected string $id = '';

    /**
     * The number of times this job has been attempted.
     */
    protected int $attempts = 0;

    /**
     * The queue this job should be dispatched to.
     */
    protected string $queue = 'default';

    /**
     * The number of seconds to delay before processing this job.
     */
    protected int $delay = 0;

    /**
     * The maximum number of attempts for this job.
     */
    protected int $maxAttempts = 1;

    /**
     * The number of seconds to wait before retrying a failed job.
     */
    protected int $retryDelay = 0;

    /**
     * The priority of this job (higher numbers = higher priority).
     */
    protected int $priority = 0;

    /**
     * Job timeout in seconds.
     */
    protected int $timeout = 60;

    /**
     * Execute the job.
     */
    abstract public function handle(): void;

    /**
     * Create a Job instance from a JobPayload.
     */
    final public static function fromPayload(JobPayloadInterface $payload): static
    {
        $data = $payload->getData();
        $options = $payload->getOptions();

        // Get the job class from options
        $jobClass = $options['class'] ?? static::class;

        if (! class_exists($jobClass)) {
            throw new InvalidArgumentException("Job class {$jobClass} does not exist");
        }

        if (! is_subclass_of($jobClass, self::class)) {
            throw new InvalidArgumentException("Job class {$jobClass} must extend ".self::class);
        }

        // Create job instance
        $job = new $jobClass();

        // Restore job state from data
        $job->restoreFromData($data);

        // Restore job metadata
        $job->id = $payload->getId();
        $job->attempts = $payload->getAttempts();
        $job->queue = $options['queue'] ?? 'default';
        $job->delay = $options['delay'] ?? 0;
        $job->maxAttempts = $options['max_attempts'] ?? 1;
        $job->retryDelay = $options['retry_delay'] ?? 0;
        $job->priority = $options['priority'] ?? 0;
        $job->timeout = $options['timeout'] ?? 60;

        return $job;
    }

    /**
     * Handle job failure.
     */
    public function failed(Throwable $exception): void
    {
        // Override in concrete jobs to handle failures
    }

    /**
     * Determine if the job should be retried.
     */
    public function shouldRetry(Throwable $exception): bool
    {
        return $this->attempts < $this->maxAttempts;
    }

    /**
     * Get the job's unique identifier.
     */
    final public function getId(): string
    {
        return $this->id;
    }

    /**
     * Set the job's unique identifier.
     */
    public function setId(string $id): self
    {
        if ($id === '' || $id === '0') {
            throw new InvalidArgumentException('Job ID cannot be empty');
        }

        $this->id = $id;

        return $this;
    }

    /**
     * Get the number of attempts.
     */
    final public function getAttempts(): int
    {
        return $this->attempts;
    }

    /**
     * Set the number of attempts.
     */
    final public function setAttempts(int $attempts): self
    {
        if ($attempts < 0) {
            throw new InvalidArgumentException('Attempts cannot be negative');
        }

        $this->attempts = $attempts;

        return $this;
    }

    /**
     * Increment the attempt count.
     */
    final public function incrementAttempts(): self
    {
        $this->attempts++;

        return $this;
    }

    /**
     * Get the queue name.
     */
    final public function getQueue(): string
    {
        return $this->queue;
    }

    /**
     * Set the queue name.
     */
    public function onQueue(string $queue): self
    {
        if ($queue === '' || $queue === '0') {
            throw new InvalidArgumentException('Queue name cannot be empty');
        }

        $this->queue = $queue;

        return $this;
    }

    /**
     * Get the delay in seconds.
     */
    public function getDelay(): int
    {
        return $this->delay;
    }

    /**
     * Set the delay in seconds.
     */
    final public function delay(int $seconds): self
    {
        if ($seconds < 0) {
            throw new InvalidArgumentException('Delay cannot be negative');
        }

        $this->delay = $seconds;

        return $this;
    }

    /**
     * Get the maximum number of attempts.
     */
    final public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    /**
     * Set the maximum number of attempts.
     */
    final public function tries(int $attempts): self
    {
        if ($attempts < 1) {
            throw new InvalidArgumentException('Max attempts must be at least 1');
        }

        $this->maxAttempts = $attempts;

        return $this;
    }

    /**
     * Get the retry delay in seconds.
     */
    final public function getRetryDelay(): int
    {
        return $this->retryDelay;
    }

    /**
     * Set the retry delay in seconds.
     */
    final public function retryAfter(int $seconds): self
    {
        if ($seconds < 0) {
            throw new InvalidArgumentException('Retry delay cannot be negative');
        }

        $this->retryDelay = $seconds;

        return $this;
    }

    /**
     * Get the job priority.
     */
    final public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * Set the job priority.
     */
    final public function priority(int $priority): self
    {
        if ($priority < 0) {
            throw new InvalidArgumentException('Priority cannot be negative');
        }

        $this->priority = $priority;

        return $this;
    }

    /**
     * Get the job timeout in seconds.
     */
    final public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * Set the job timeout in seconds.
     */
    final public function timeout(int $seconds): self
    {
        if ($seconds <= 0) {
            throw new InvalidArgumentException('Timeout must be positive');
        }

        $this->timeout = $seconds;

        return $this;
    }

    /**
     * Create a JobPayload from this job.
     */
    public function toPayload(): JobPayloadInterface
    {
        $data = $this->jsonSerialize();

        $options = [
            'queue' => $this->queue,
            'delay' => $this->delay,
            'max_attempts' => $this->maxAttempts,
            'retry_delay' => $this->retryDelay,
            'priority' => $this->priority,
            'timeout' => $this->timeout,
            'class' => static::class,
        ];

        $payload = new JobPayload($data, $options);

        if ($this->id !== '' && $this->id !== '0') {
            $payload->setId($this->id);
        }

        return $payload->setAttempts($this->attempts);
    }

    /**
     * Serialize the job to JSON.
     */
    final public function jsonSerialize(): array
    {
        $reflection = new ReflectionClass($this);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PRIVATE);

        $data = [];
        foreach ($properties as $property) {
            $property->setAccessible(true);
            $name = $property->getName();

            // Skip internal job metadata
            if (in_array($name, ['id', 'attempts', 'queue', 'delay', 'maxAttempts', 'retryDelay', 'priority', 'timeout'])) {
                continue;
            }

            $data[$name] = $property->getValue($this);
        }

        return $data;
    }

    /**
     * Get a display name for this job.
     */
    final public function displayName(): string
    {
        return static::class;
    }

    /**
     * Restore job state from serialized data.
     */
    protected function restoreFromData(array $data): void
    {
        $reflection = new ReflectionClass($this);

        foreach ($data as $name => $value) {
            if ($reflection->hasProperty($name)) {
                $property = $reflection->getProperty($name);
                $property->setAccessible(true);
                $property->setValue($this, $value);
            }
        }
    }
}
