<?php

declare(strict_types=1);

namespace FlowCore\Dispatcher;

use FlowCore\Queue\QueueManager;

final readonly class JobDispatcher
{
    public function __construct(private QueueManager $queueManager) {}

    public function dispatch(string $name, array $payload = [], ?int $delaySeconds = null): void
    {
        $job = [
            'name' => $name,
            'payload' => $payload,
            'dispatched_at' => time(),
            'delay_until' => $delaySeconds !== null && $delaySeconds !== 0 ? time() + $delaySeconds : null,
        ];
        $this->queueManager->enqueue($job);
    }
}
