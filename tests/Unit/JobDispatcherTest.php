<?php

declare(strict_types=1);

use FlowCore\Dispatcher\JobDispatcher;
use FlowCore\Queue\QueueManager;
use FlowCore\Queue\RedisQueue;
use FlowCore\Storage\RedisClient;
use PHPUnit\Framework\TestCase;

final class JobDispatcherTest extends TestCase
{
    protected function setUp(): void
    {
        $redis = (new RedisClient())->client();
        $redis->del(['flowcore:queue']);
    }

    public function test_dispatch_job(): void
    {
        $dispatcher = new JobDispatcher(
            new QueueManager(
                new RedisQueue(
                    new RedisClient()
                )
            )
        );

        $dispatcher->dispatch('testjob', ['foo' => 'bar']);
        $redis = (new RedisClient())->client();
        $job = $redis->lpop('flowcore:queue');
        $jobArr = json_decode($job, true);

        $this->assertSame('testjob', $jobArr['name']);
        $this->assertSame(['foo' => 'bar'], $jobArr['payload']);
        $this->assertArrayHasKey('dispatched_at', $jobArr);
    }
}
