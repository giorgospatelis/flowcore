<?php

declare(strict_types=1);

namespace FlowCore\Worker;

use FlowCore\Job\Registry;
use FlowCore\Queue\QueueManager;

final class JobWorker
{
    private bool $running = true;

    /**
     * JobWorker constructor.
     *
     * @param  QueueManager  $queueManager  The queue manager to use for job processing.
     * @param  Registry  $registry  The registry to resolve job classes.
     */
    public function __construct(
        private QueueManager $queueManager,
        private Registry $registry
    ) {}

    public function stop(): void
    {
        $this->running = false;
    }

    public function run(): void
    {
        while ($this->running) {
            $jobData = $this->queueManager->dequeue();
            if ($jobData === null || $jobData === []) {
                usleep(500000);

                continue;
            }

            $name = $jobData['name'] ?? null;
            $payload = $jobData['payload'] ?? [];
            if (! is_string($name) || ! is_array($payload)) {
                continue;
            }

            // Fix for array type below
            if (! is_array($payload) || array_is_list($payload)) {
                $payload = [];
            }

            $job = $this->registry->resolve($name);
            $job->run($payload);
        }
    }

    private static function isStringKeyedArray(array $arr): bool
    {
        foreach (array_keys($arr) as $key) {
            if (! is_string($key)) {
                return false;
            }
        }

        return true;
    }
}
