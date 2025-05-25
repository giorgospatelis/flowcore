<?php

declare(strict_types=1);

namespace Tests\Support;

use FlowCore\Core\Job;

class TestQueueJob extends Job
{
    public function __construct(
        public string $message = 'test',
        public int $userId = 0
    ) {}

    public function handle(): void
    {
        // Test implementation
    }
}
