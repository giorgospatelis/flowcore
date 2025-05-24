<?php

declare(strict_types=1);

namespace FlowCore\Queue;

final readonly class QueueManager
{
    /**
     * @param  QueueInterface  $queue  The queue implementation to use.
     */
    private QueueInterface $queue;

    public function __construct(QueueInterface $queue)
    {
        $this->queue = $queue;
    }

    /**
     * Enqueue a job into the queue.
     *
     * @param  array<string, mixed>  $job  The job to enqueue, typically containing 'name' and 'payload'.
     */
    public function enqueue(array $job): void
    {
        $this->queue->enqueue($job);
    }

    /**
     * Dequeue a job from the queue.
     *
     * @return array<string, mixed>|null The dequeued job, or null if the queue is empty.
     */
    public function dequeue(): ?array
    {
        return $this->queue->dequeue();
    }
}
