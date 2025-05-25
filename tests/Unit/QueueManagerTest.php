<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use FlowCore\Contracts\JobPayloadInterface;
use FlowCore\Contracts\QueueDriverInterface;
use FlowCore\Core\Job;
use FlowCore\Core\JobPayload;
use FlowCore\Core\QueueManager;
use FlowCore\Support\Exceptions\QueueException;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestQueueJob;

final class QueueManagerTest extends TestCase
{
    private QueueDriverInterface&MockObject $driver;
    private QueueManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->driver = $this->createMock(QueueDriverInterface::class);
        $this->manager = new QueueManager(['default' => $this->driver]);
    }

    public function test_push_delegates_to_driver_and_returns_job_id(): void
    {
        $job = $this->createMock(Job::class);
        $payload = $this->createMock(JobPayloadInterface::class);

        $job->method('getQueue')->willReturn('default');
        $job->method('getDelay')->willReturn(0);
        $job->method('toPayload')->willReturn($payload);
        $payload->method('getId')->willReturn('');
        $payload->expects($this->once())->method('setId');
        $job->expects($this->once())->method('setId');
        $this->driver->expects($this->once())->method('push')->with('queue', $payload);

        $result = $this->manager->push('queue', $job);
        $this->assertIsString($result);
    }

    public function test_later_delegates_to_driver_and_returns_job_id(): void
    {
        $job = $this->createMock(Job::class);
        $payload = $this->createMock(JobPayloadInterface::class);

        $job->method('toPayload')->willReturn($payload);
        $payload->method('getId')->willReturn('');
        $payload->expects($this->once())->method('setId');
        $job->expects($this->once())->method('setId');
        $this->driver->expects($this->once())->method('later')->with('queue', $payload, 10);

        $result = $this->manager->later('queue', $job, 10);
        $this->assertIsString($result);
    }

    public function test_pop_returns_null_when_no_job(): void
    {
        $this->driver->method('pop')->willReturn(null);
        $this->assertNull($this->manager->pop('queue'));
    }

    public function test_bulk_pushes_jobs_and_returns_ids(): void
    {
        $job1 = $this->createMock(Job::class);
        $job2 = $this->createMock(Job::class);
        $payload1 = $this->createMock(JobPayloadInterface::class);
        $payload2 = $this->createMock(JobPayloadInterface::class);

        $job1->method('toPayload')->willReturn($payload1);
        $job2->method('toPayload')->willReturn($payload2);
        $payload1->method('getId')->willReturn('');
        $payload2->method('getId')->willReturn('');
        $payload1->expects($this->once())->method('setId');
        $payload2->expects($this->once())->method('setId');
        $job1->expects($this->once())->method('setId');
        $job2->expects($this->once())->method('setId');
        $this->driver->method('supportsBatchOperations')->willReturn(true);
        $this->driver->expects($this->once())->method('pushBatch');

        $ids = $this->manager->bulk('queue', [$job1, $job2]);
        $this->assertCount(2, $ids);
    }

    public function test_size_delegates_to_driver(): void
    {
        $this->driver->expects($this->once())->method('size')->with('queue')->willReturn(5);
        $this->assertSame(5, $this->manager->size('queue'));
    }

    public function test_clear_delegates_to_driver(): void
    {
        $this->driver->expects($this->once())->method('clear')->with('queue');
        $this->manager->clear('queue');
    }

    public function test_getQueues_delegates_to_driver(): void
    {
        $this->driver->expects($this->once())->method('queues')->willReturn(['queue1', 'queue2']);
        $this->assertSame(['queue1', 'queue2'], $this->manager->getQueues());
    }

    public function test_ack_delegates_to_driver(): void
    {
        $this->driver->expects($this->once())->method('ack')->with('queue', 'jobid');
        $this->manager->ack('queue', 'jobid');
    }

    public function test_nack_delegates_to_driver(): void
    {
        $this->driver->expects($this->once())->method('nack')->with('queue', 'jobid');
        $this->manager->nack('queue', 'jobid');
    }

    public function test_release_delegates_to_driver(): void
    {
        $this->driver->expects($this->once())->method('release')->with('queue', 'jobid', 3);
        $this->manager->release('queue', 'jobid', 3);
    }

    public function test_delete_delegates_to_driver_and_returns_bool(): void
    {
        $this->driver->expects($this->once())->method('delete')->with('queue', 'jobid')->willReturn(true);
        $this->assertTrue($this->manager->delete('queue', 'jobid'));
    }

    public function test_find_returns_null_when_not_found(): void
    {
        $this->driver->method('get')->willReturn(null);
        $this->assertNull($this->manager->find('queue', 'jobid'));
    }

    public function test_add_and_has_connection(): void
    {
        $mockDriver = $this->createMock(QueueDriverInterface::class);
        $this->manager->addConnection('foo', $mockDriver);
        $this->assertTrue($this->manager->hasConnection('foo'));
    }

    public function test_set_and_get_default_connection(): void
    {
        $this->manager->setDefaultConnection('foo');
        $this->assertSame('foo', $this->manager->getDefaultConnection());
    }

    public function test_getConnectionNames_returns_all_names(): void
    {
        $mockDriver = $this->createMock(QueueDriverInterface::class);
        $this->manager->addConnection('foo', $mockDriver);
        $names = $this->manager->getConnectionNames();
        $this->assertContains('default', $names);
        $this->assertContains('foo', $names);
    }

    public function test_getConnectionInfo_returns_expected_keys(): void
    {
        $info = $this->manager->getConnectionInfo();
        $this->assertArrayHasKey('name', $info);
        $this->assertArrayHasKey('driver', $info);
        $this->assertArrayHasKey('supports_priority', $info);
        $this->assertArrayHasKey('supports_delayed', $info);
        $this->assertArrayHasKey('supports_transactions', $info);
        $this->assertArrayHasKey('supports_streaming', $info);
    }

    public function test_getStats_returns_stats_for_queues(): void
    {
        $this->driver->method('size')->willReturn(2);
        $this->driver->method('queues')->willReturn(['queue1']);
        $stats = $this->manager->getStats();
        $this->assertArrayHasKey('queue1', $stats);
        $this->assertSame(2, $stats['queue1']['size']);
    }
}
