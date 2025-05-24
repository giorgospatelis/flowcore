<?php

declare(strict_types=1);

namespace FlowCore\Dispatcher;

use FlowCore\Queue\QueueManager;

final readonly class JobDispatcher
{
    /**
     * JobDispatcher constructor.
     *
     * @param  QueueManager  $queueManager  The queue manager to handle job dispatching.
     */
    public function __construct(private QueueManager $queueManager) {}

    /**
     * Dispatch a job with the given name and payload.
     *
     * @param  string  $name  The name of the job to dispatch.
     * @param  array<string, mixed>  $payload  The data to process in the job.
     * @param  int|null  $delaySeconds  Optional delay in seconds before the job should be processed.
     */
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
