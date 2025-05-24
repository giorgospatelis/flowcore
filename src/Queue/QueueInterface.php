<?php

declare(strict_types=1);

namespace FlowCore\Queue;

interface QueueInterface
{
    /**
     * Enqueue a job into the queue.
     *
     * @param  array<string, mixed>  $job  The job to enqueue, typically containing 'name' and 'payload'.
     */
    public function enqueue(array $job): void;

    /**
     * Dequeue a job from the queue.
     *
     * @return array<string, mixed>|null The dequeued job, or null if the queue is empty.
     */
    public function dequeue(): ?array;
}
