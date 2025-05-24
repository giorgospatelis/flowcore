<?php

declare(strict_types=1);

use FlowCore\Job\AbstractJob;
use FlowCore\Job\Registry;
use FlowCore\Queue\QueueManager;
use FlowCore\Queue\RedisQueue;
use FlowCore\Storage\RedisClient;
use PHPUnit\Framework\TestCase;

final class TestJob extends AbstractJob
{
    public static array $ran = [];

    public function run(array $payload): void
    {
        self::$ran[] = $payload;
    }
}

final class JobWorkerTest extends TestCase
{
    protected function setUp(): void
    {
        TestJob::$ran = [];
        $redis = (new RedisClient())->client();
        $redis->del(['flowcore:queue']);
    }

    public function test_worker_processes_job_once(): void
    {
        $registry = new Registry();
        $registry->register('testjob', TestJob::class);

        $queueManager = new QueueManager(
            new RedisQueue(
                new RedisClient()
            )
        );

        // Enqueue a job
        $queueManager->enqueue(['name' => 'testjob', 'payload' => ['foo' => 'bar']]);

        // Simulate one iteration of the worker loop
        $jobData = $queueManager->dequeue();
        if ($jobData) {
            $job = $registry->resolve($jobData['name']);
            $job->run($jobData['payload']);
        }

        $this->assertSame([['foo' => 'bar']], TestJob::$ran);
    }
}
