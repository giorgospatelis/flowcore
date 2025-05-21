<?php

declare(strict_types=1);

namespace FlowCore\Job;

interface JobInterface
{
    public function run(array $payload): void;
}
