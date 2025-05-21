<?php

namespace FlowCore\Queue;

class QueueManager
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
