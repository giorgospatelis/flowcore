<?php

declare(strict_types=1);

namespace Tests\Unit;

use FlowCore\Contracts\JobPayloadInterface;
use FlowCore\Core\JobPayload;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class JobPayloadTest extends TestCase
{
    private JobPayload $jobPayload;

    protected function setUp(): void
    {
        $this->jobPayload = new JobPayload([
            'user_id' => 123,
            'email' => 'test@example.com',
        ], [
            'priority' => 1,
            'max_attempts' => 3,
        ]);
    }

    public function test_can_create_with_empty_data_and_options(): void
    {
        $payload = new JobPayload();

        $this->assertSame([], $payload->getData());
        $this->assertSame([], $payload->getOptions());
        $this->assertSame(0, $payload->getAttempts());
        $this->assertSame('', $payload->getId());
    }

    public function test_can_create_with_data_and_options(): void
    {
        $data = ['user_id' => 123];
        $options = ['priority' => 2];

        $payload = new JobPayload($data, $options);

        $this->assertSame($data, $payload->getData());
        $this->assertSame($options, $payload->getOptions());
    }

    public function test_can_set_and_get_id(): void
    {
        $id = 'job-12345';
        $result = $this->jobPayload->setId($id);

        $this->assertSame($this->jobPayload, $result);
        $this->assertSame($id, $this->jobPayload->getId());
    }

    public function test_cannot_set_empty_id(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Job ID cannot be empty');

        $this->jobPayload->setId('');
    }

    public function test_can_get_data(): void
    {
        $expected = [
            'user_id' => 123,
            'email' => 'test@example.com',
        ];

        $this->assertSame($expected, $this->jobPayload->getData());
    }

    public function test_can_get_options(): void
    {
        $expected = [
            'priority' => 1,
            'max_attempts' => 3,
        ];

        $this->assertSame($expected, $this->jobPayload->getOptions());
    }

    public function test_initial_attempts_is_zero(): void
    {
        $this->assertSame(0, $this->jobPayload->getAttempts());
    }

    public function test_can_increment_attempts(): void
    {
        $result = $this->jobPayload->incrementAttempts();

        $this->assertSame($this->jobPayload, $result);
        $this->assertSame(1, $this->jobPayload->getAttempts());

        $this->jobPayload->incrementAttempts();
        $this->assertSame(2, $this->jobPayload->getAttempts());
    }

    public function test_can_get_option_with_default(): void
    {
        $this->assertSame(1, $this->jobPayload->getOption('priority'));
        $this->assertSame(3, $this->jobPayload->getOption('max_attempts'));
        $this->assertNull($this->jobPayload->getOption('nonexistent'));
        $this->assertSame('default', $this->jobPayload->getOption('nonexistent', 'default'));
    }

    public function test_can_check_if_has_option(): void
    {
        $this->assertTrue($this->jobPayload->hasOption('priority'));
        $this->assertTrue($this->jobPayload->hasOption('max_attempts'));
        $this->assertFalse($this->jobPayload->hasOption('nonexistent'));
    }

    public function test_can_create_copy_with_option(): void
    {
        $newPayload = $this->jobPayload->withOption('timeout', 30);

        $this->assertNotSame($this->jobPayload, $newPayload);
        $this->assertFalse($this->jobPayload->hasOption('timeout'));
        $this->assertTrue($newPayload->hasOption('timeout'));
        $this->assertSame(30, $newPayload->getOption('timeout'));
    }

    public function test_can_create_copy_with_data(): void
    {
        $newData = ['different' => 'data'];
        $newPayload = $this->jobPayload->withData($newData);

        $this->assertNotSame($this->jobPayload, $newPayload);
        $this->assertNotSame($newData, $this->jobPayload->getData());
        $this->assertSame($newData, $newPayload->getData());
    }

    public function test_can_serialize(): void
    {
        $this->jobPayload->setId('test-id');
        $this->jobPayload->incrementAttempts();

        $serialized = $this->jobPayload->serialize();
        $decoded = json_decode($serialized, true);

        $expected = [
            'id' => 'test-id',
            'data' => [
                'user_id' => 123,
                'email' => 'test@example.com',
            ],
            'options' => [
                'priority' => 1,
                'max_attempts' => 3,
            ],
            'attempts' => 1,
        ];

        $this->assertSame($expected, $decoded);
    }

    public function test_can_unserialize(): void
    {
        $data = [
            'id' => 'test-job-id',
            'data' => ['user_id' => 456],
            'options' => ['priority' => 2],
            'attempts' => 3,
        ];

        $serialized = json_encode($data);
        $payload = JobPayload::unserialize($serialized);

        $this->assertInstanceOf(JobPayload::class, $payload);
        $this->assertSame('test-job-id', $payload->getId());
        $this->assertSame(['user_id' => 456], $payload->getData());
        $this->assertSame(['priority' => 2], $payload->getOptions());
        $this->assertSame(3, $payload->getAttempts());
    }

    public function test_unserialize_throws_exception_for_invalid_json(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON data provided');

        JobPayload::unserialize('invalid-json');
    }

    public function test_unserialize_throws_exception_for_non_object_json(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Serialized data must be a JSON object');

        JobPayload::unserialize('"string"');
    }

    public function test_unserialize_throws_exception_for_missing_fields(): void
    {
        $incompleteData = ['id' => 'test', 'data' => []];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: options');

        JobPayload::unserialize(json_encode($incompleteData));
    }

    public function test_unserialize_validates_field_types(): void
    {
        $invalidData = [
            'id' => 123, // should be string
            'data' => [],
            'options' => [],
            'attempts' => 0,
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Job ID must be a string');

        JobPayload::unserialize(json_encode($invalidData));
    }

    public function test_constructor_validates_data_is_json_serializable(): void
    {
        $resource = fopen('php://memory', 'r');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Job data must be JSON serializable');

        new JobPayload(['resource' => $resource]);

        fclose($resource);
    }

    public function test_constructor_validates_options_is_json_serializable(): void
    {
        $resource = fopen('php://memory', 'r');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Job options must be JSON serializable');

        new JobPayload([], ['resource' => $resource]);

        fclose($resource);
    }

    public function test_constructor_validates_priority_option(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Priority must be a non-negative integer');

        new JobPayload([], ['priority' => -1]);
    }

    public function test_constructor_validates_delay_option(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Delay must be a non-negative integer');

        new JobPayload([], ['delay' => -5]);
    }

    public function test_constructor_validates_max_attempts_option(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Max attempts must be a positive integer');

        new JobPayload([], ['max_attempts' => 0]);
    }

    public function test_with_data_validates_json_serializable(): void
    {
        $resource = fopen('php://memory', 'r');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Job data must be JSON serializable');

        $this->jobPayload->withData(['resource' => $resource]);

        fclose($resource);
    }

    public function test_serialize_and_unserialize_roundtrip(): void
    {
        $original = new JobPayload(
            ['user_id' => 789, 'message' => 'Hello World'],
            ['priority' => 5, 'max_attempts' => 2, 'delay' => 10]
        );
        $original->setId('roundtrip-test');
        $original->incrementAttempts();
        $original->incrementAttempts();

        $serialized = $original->serialize();
        $restored = JobPayload::unserialize($serialized);

        $this->assertSame($original->getId(), $restored->getId());
        $this->assertSame($original->getData(), $restored->getData());
        $this->assertSame($original->getOptions(), $restored->getOptions());
        $this->assertSame($original->getAttempts(), $restored->getAttempts());
    }

    public function test_implements_job_payload_interface(): void
    {
        $this->assertInstanceOf(JobPayloadInterface::class, $this->jobPayload);
    }
}
