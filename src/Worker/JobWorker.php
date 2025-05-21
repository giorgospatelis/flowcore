<?php

declare(strict_types=1);

namespace FlowCore\Worker;

use FlowCore\Job\Registry;
use FlowCore\Queue\QueueManager;

final readonly class JobWorker
{
    public function __construct(
        private QueueManager $queueManager,
        private Registry $registry
    ) {}

    public function run(): void
    {
        while (true) {
            $jobData = $this->queueManager->dequeue();
            if ($jobData === null || $jobData === []) {
                usleep(500000); // wait 0.5s

                continue;
            }

            $job = $this->registry->resolve($jobData['name']);
            $job->run($jobData['payload']);
        }
    }
}
