<?php

declare(strict_types=1);

namespace FlowCore\Queue;

final readonly class QueueManager
{
    public function __construct(private QueueInterface $queue) {}

    public function enqueue(array $job): void
    {
        $this->queue->enqueue($job);
    }

    public function dequeue(): ?array
    {
        return $this->queue->dequeue();
    }
}
