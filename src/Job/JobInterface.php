<?php

namespace FlowCore\Job;

interface JobInterface
{
    public function run(array $payload): void;
}
