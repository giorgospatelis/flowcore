<?php

namespace FlowCore\Dispatcher;

use FlowCore\Queue\QueueManager;

class JobDispatcher
{
    public function __construct(private QueueManager $queueManager) {}

    public function dispatch(string $name, array $payload = [], ?int $delaySeconds = null): void
    {
        $job = [
            'name' => $name,
            'payload' => $payload,
            'dispatched_at' => time(),
            'delay_until' => $delaySeconds ? time() + $delaySeconds : null,
        ];
        $this->queueManager->enqueue($job);
    }
}
