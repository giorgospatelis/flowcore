<?php

declare(strict_types=1);

use FlowCore\Queue\QueueManager;
use FlowCore\Queue\RedisQueue;
use FlowCore\Storage\RedisClient;
use PHPUnit\Framework\TestCase;

final class QueueManagerTest extends TestCase
{
    protected function setUp(): void
    {
        $redis = (new RedisClient())->client();
        $redis->del(['flowcore:queue']);
    }

    public function test_enqueue_and_dequeue(): void
    {
        $queue = new RedisQueue(new RedisClient());
        $manager = new QueueManager($queue);

        $job = ['name' => 'test', 'payload' => ['b' => 2]];
        $manager->enqueue($job);

        $dequeued = $manager->dequeue();
        $this->assertSame($job, $dequeued);

        $this->assertNull($manager->dequeue());
    }
}
