<?php

declare(strict_types=1);

namespace FlowCore\Job;

interface JobInterface
{
    /**
     * Run the job with the given payload.
     *
     * @param  array<string, mixed>  $payload  The data to process in the job.
     */
    public function run(array $payload): void;
}
