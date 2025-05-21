<?php

declare(strict_types=1);

namespace FlowCore\Queue;

interface QueueInterface
{
    public function enqueue(array $job): void;

    public function dequeue(): ?array;
}
