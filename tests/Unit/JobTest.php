<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use FlowCore\Core\Job;
use FlowCore\Core\JobPayload;
use FlowCore\Contracts\JobPayloadInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;
use Tests\Support\TestQueueJob;

class TestJobWithFailureHandling extends Job
{
    public bool $failureHandled = false;
    public ?Throwable $failureException = null;

    public function handle(): void
    {
        throw new RuntimeException('Test failure');
    }

    public function failed(Throwable $exception): void
    {
        $this->failureHandled = true;
        $this->failureException = $exception;
    }

    public function shouldRetry(Throwable $exception): bool
    {
        return $exception->getMessage() !== 'Do not retry';
    }
}

class TestJobWithCustomRetry extends Job
{
    public function handle(): void {}

    public function shouldRetry(Throwable $exception): bool
    {
        return false; // Never retry
    }
}

final class JobTest extends TestCase
{
    private TestQueueJob $job;

    protected function setUp(): void
    {
        $this->job = new TestQueueJob('Hello World', 123);
    }

    public function test_can_create_job(): void
    {
        $job = new TestQueueJob('Test message', 456);

        $this->assertSame('Test message', $job->message);
        $this->assertSame(456, $job->userId);
        $this->assertSame('default', $job->getQueue());
        $this->assertSame(0, $job->getAttempts());
    }

    public function test_can_set_and_get_job_id(): void
    {
        $jobId = 'test-job-123';
        $result = $this->job->setId($jobId);

        $this->assertSame($this->job, $result);
        $this->assertSame($jobId, $this->job->getId());
    }

    public function test_cannot_set_empty_job_id(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Job ID cannot be empty');

        $this->job->setId('');
    }

    public function test_can_set_and_get_attempts(): void
    {
        $result = $this->job->setAttempts(5);

        $this->assertSame($this->job, $result);
        $this->assertSame(5, $this->job->getAttempts());
    }

    public function test_cannot_set_negative_attempts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Attempts cannot be negative');

        $this->job->setAttempts(-1);
    }

    public function test_can_increment_attempts(): void
    {
        $result = $this->job->incrementAttempts();

        $this->assertSame($this->job, $result);
        $this->assertSame(1, $this->job->getAttempts());

        $this->job->incrementAttempts();
        $this->assertSame(2, $this->job->getAttempts());
    }

    public function test_can_set_queue(): void
    {
        $result = $this->job->onQueue('high-priority');

        $this->assertSame($this->job, $result);
        $this->assertSame('high-priority', $this->job->getQueue());
    }

    public function test_cannot_set_empty_queue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Queue name cannot be empty');

        $this->job->onQueue('');
    }

    public function test_can_set_delay(): void
    {
        $result = $this->job->delay(300);

        $this->assertSame($this->job, $result);
        $this->assertSame(300, $this->job->getDelay());
    }

    public function test_cannot_set_negative_delay(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Delay cannot be negative');

        $this->job->delay(-10);
    }

    public function test_can_set_max_attempts(): void
    {
        $result = $this->job->tries(5);

        $this->assertSame($this->job, $result);
        $this->assertSame(5, $this->job->getMaxAttempts());
    }

    public function test_cannot_set_zero_max_attempts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Max attempts must be at least 1');

        $this->job->tries(0);
    }

    public function test_can_set_retry_delay(): void
    {
        $result = $this->job->retryAfter(120);

        $this->assertSame($this->job, $result);
        $this->assertSame(120, $this->job->getRetryDelay());
    }

    public function test_cannot_set_negative_retry_delay(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Retry delay cannot be negative');

        $this->job->retryAfter(-5);
    }

    public function test_can_set_priority(): void
    {
        $result = $this->job->priority(10);

        $this->assertSame($this->job, $result);
        $this->assertSame(10, $this->job->getPriority());
    }

    public function test_cannot_set_negative_priority(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Priority cannot be negative');

        $this->job->priority(-1);
    }

    public function test_can_set_timeout(): void
    {
        $result = $this->job->timeout(300);

        $this->assertSame($this->job, $result);
        $this->assertSame(300, $this->job->getTimeout());
    }

    public function test_cannot_set_zero_or_negative_timeout(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Timeout must be positive');

        $this->job->timeout(0);
    }

    public function test_method_chaining(): void
    {
        $result = $this->job
            ->onQueue('emails')
            ->delay(60)
            ->tries(3)
            ->retryAfter(30)
            ->priority(5)
            ->timeout(120);

        $this->assertSame($this->job, $result);
        $this->assertSame('emails', $this->job->getQueue());
        $this->assertSame(60, $this->job->getDelay());
        $this->assertSame(3, $this->job->getMaxAttempts());
        $this->assertSame(30, $this->job->getRetryDelay());
        $this->assertSame(5, $this->job->getPriority());
        $this->assertSame(120, $this->job->getTimeout());
    }

    public function test_should_retry_by_default(): void
    {
        $exception = new RuntimeException('Test error');

        // Should retry when attempts < max attempts
        $this->assertTrue($this->job->shouldRetry($exception));

        // Should not retry when attempts >= max attempts
        $this->job->setAttempts(1);
        $this->assertFalse($this->job->shouldRetry($exception));
    }

    public function test_should_retry_with_custom_logic(): void
    {
        $job = new TestJobWithCustomRetry();
        $exception = new RuntimeException('Test error');

        $this->assertFalse($job->shouldRetry($exception));
    }

    public function test_failed_method_can_be_overridden(): void
    {
        $job = new TestJobWithFailureHandling();
        $exception = new RuntimeException('Test failure');

        $job->failed($exception);

        $this->assertTrue($job->failureHandled);
        $this->assertSame($exception, $job->failureException);
    }

    public function test_can_convert_to_payload(): void
    {
        $this->job
            ->setId('test-123')
            ->onQueue('emails')
            ->delay(60)
            ->tries(3)
            ->retryAfter(30)
            ->priority(5)
            ->timeout(120)
            ->setAttempts(2);

        $payload = $this->job->toPayload();

        $this->assertInstanceOf(JobPayloadInterface::class, $payload);
        $this->assertSame('test-123', $payload->getId());
        $this->assertSame(2, $payload->getAttempts());

        $data = $payload->getData();
        $this->assertSame('Hello World', $data['message']);
        $this->assertSame(123, $data['userId']);

        $options = $payload->getOptions();
        $this->assertSame('emails', $options['queue']);
        $this->assertSame(60, $options['delay']);
        $this->assertSame(3, $options['max_attempts']);
        $this->assertSame(30, $options['retry_delay']);
        $this->assertSame(5, $options['priority']);
        $this->assertSame(120, $options['timeout']);
        $this->assertSame(TestQueueJob::class, $options['class']);
    }

    public function test_can_create_from_payload(): void
    {
        $data = [
            'message' => 'Restored message',
            'userId' => 789
        ];

        $options = [
            'queue' => 'restored-queue',
            'delay' => 45,
            'max_attempts' => 4,
            'retry_delay' => 15,
            'priority' => 8,
            'timeout' => 180,
            'class' => TestQueueJob::class
        ];

        $payload = new JobPayload($data, $options);
        $payload->setId('restored-job-456');
        $payload->setAttempts(3);

        $job = TestQueueJob::fromPayload($payload);

        $this->assertInstanceOf(TestQueueJob::class, $job);
        $this->assertSame('Restored message', $job->message);
        $this->assertSame(789, $job->userId);
        $this->assertSame('restored-job-456', $job->getId());
        $this->assertSame(3, $job->getAttempts());
        $this->assertSame('restored-queue', $job->getQueue());
        $this->assertSame(45, $job->getDelay());
        $this->assertSame(4, $job->getMaxAttempts());
        $this->assertSame(15, $job->getRetryDelay());
        $this->assertSame(8, $job->getPriority());
        $this->assertSame(180, $job->getTimeout());
    }

    public function test_from_payload_validates_job_class_exists(): void
    {
        $payload = new JobPayload([], ['class' => 'NonExistentJobClass']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Job class NonExistentJobClass does not exist');

        TestQueueJob::fromPayload($payload);
    }

    public function test_from_payload_validates_job_class_extends_job(): void
    {
        $payload = new JobPayload([], ['class' => 'stdClass']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Job class stdClass must extend');

        TestQueueJob::fromPayload($payload);
    }

    public function test_json_serialize_excludes_metadata(): void
    {
        $this->job->setId('test-id')->setAttempts(2);

        $serialized = $this->job->jsonSerialize();

        $this->assertSame([
            'message' => 'Hello World',
            'userId' => 123
        ], $serialized);

        // Ensure metadata is excluded
        $this->assertArrayNotHasKey('jobId', $serialized);
        $this->assertArrayNotHasKey('attempts', $serialized);
        $this->assertArrayNotHasKey('queue', $serialized);
    }

    public function test_display_name_returns_class_name(): void
    {
        $this->assertSame(TestQueueJob::class, $this->job->displayName());
    }

    public function test_payload_roundtrip_preserves_job_state(): void
    {
        $original = new TestQueueJob('Original message', 999);
        $original
            ->setId('roundtrip-123')
            ->onQueue('test-queue')
            ->delay(30)
            ->tries(2)
            ->retryAfter(10)
            ->priority(3)
            ->timeout(90)
            ->setAttempts(1);

        $payload = $original->toPayload();
        $restored = TestQueueJob::fromPayload($payload);

        $this->assertSame($original->message, $restored->message);
        $this->assertSame($original->userId, $restored->userId);
        $this->assertSame($original->getId(), $restored->getId());
        $this->assertSame($original->getAttempts(), $restored->getAttempts());
        $this->assertSame($original->getQueue(), $restored->getQueue());
        $this->assertSame($original->getDelay(), $restored->getDelay());
        $this->assertSame($original->getMaxAttempts(), $restored->getMaxAttempts());
        $this->assertSame($original->getRetryDelay(), $restored->getRetryDelay());
        $this->assertSame($original->getPriority(), $restored->getPriority());
        $this->assertSame($original->getTimeout(), $restored->getTimeout());
    }

    public function test_job_with_private_properties(): void
    {
        $job = new class('test') extends Job {
            private readonly string $privateValue;
            protected string $protectedValue;
            public string $publicValue;

            public function __construct(string $value)
            {
                $this->privateValue = $value . '_private';
                $this->protectedValue = $value . '_protected';
                $this->publicValue = $value . '_public';
            }

            public function handle(): void {}

            public function getPrivateValue(): string
            {
                return $this->privateValue;
            }

            public function getProtectedValue(): string
            {
                return $this->protectedValue;
            }
        };

        $serialized = $job->jsonSerialize();

        $this->assertArrayHasKey('privateValue', $serialized);
        $this->assertArrayHasKey('protectedValue', $serialized);
        $this->assertArrayHasKey('publicValue', $serialized);
        $this->assertSame('test_private', $serialized['privateValue']);
        $this->assertSame('test_protected', $serialized['protectedValue']);
        $this->assertSame('test_public', $serialized['publicValue']);
    }

    public function test_restore_from_data_handles_missing_properties(): void
    {
        $payload = new JobPayload([
            'message' => 'Test',
            'userId' => 456,
            'nonExistentProperty' => 'should be ignored'
        ], ['class' => TestQueueJob::class]);

        $job = TestQueueJob::fromPayload($payload);

        $this->assertSame('Test', $job->message);
        $this->assertSame(456, $job->userId);
        // nonExistentProperty should be silently ignored
    }

    public function test_default_values(): void
    {
        $job = new TestQueueJob();

        $this->assertSame('', $job->getId());
        $this->assertSame(0, $job->getAttempts());
        $this->assertSame('default', $job->getQueue());
        $this->assertSame(0, $job->getDelay());
        $this->assertSame(1, $job->getMaxAttempts());
        $this->assertSame(0, $job->getRetryDelay());
        $this->assertSame(0, $job->getPriority());
        $this->assertSame(60, $job->getTimeout());
    }
}
