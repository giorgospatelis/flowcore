<?php

declare(strict_types=1);

use FlowCore\Queue\RedisQueue;
use FlowCore\Storage\RedisClient;
use PHPUnit\Framework\TestCase;

final class RedisQueueTest extends TestCase
{
    protected function setUp(): void
    {
        $redis = (new RedisClient())->client();
        $redis->del(['flowcore:queue']);
    }

    public function test_enqueue_and_dequeue(): void
    {
        $redisClient = new RedisClient();
        $queue = new RedisQueue($redisClient);

        $job = ['name' => 'test', 'payload' => ['a' => 1]];
        $queue->enqueue($job);

        $dequeued = $queue->dequeue();
        $this->assertSame($job, $dequeued);

        $this->assertNull($queue->dequeue());
    }
}
