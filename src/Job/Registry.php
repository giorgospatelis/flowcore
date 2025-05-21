<?php

namespace FlowCore\Job;

class Registry
{
    protected array $map = [];

    public function register(string $name, string $class): void
    {
        $this->map[$name] = $class;
    }

    public function resolve(string $name): JobInterface
    {
        if (!isset($this->map[$name])) {
            throw new \RuntimeException("Job '$name' not found.");
        }

        return new ($this->map[$name])();
    }
}
