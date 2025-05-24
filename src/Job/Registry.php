<?php

declare(strict_types=1);

namespace FlowCore\Job;

use RuntimeException;

final class Registry
{
    /**
     * @var array<string, class-string<JobInterface>>
     */
    private array $map = [];

    /**
     * Register a job with the registry.
     *
     * @param  string  $name  The name of the job.
     * @param  class-string<JobInterface>  $class  The class name of the job.
     */
    public function register(string $name, string $class): void
    {
        $this->map[$name] = $class;
    }

    /**
     * Resolve a job by its name.
     *
     * @param  string  $name  The name of the job to resolve.
     * @return JobInterface The resolved job instance.
     *
     * @throws RuntimeException If the job is not found.
     */
    public function resolve(string $name): JobInterface
    {
        if (! isset($this->map[$name])) {
            throw new RuntimeException("Job '$name' not found.");
        }

        return new ($this->map[$name])();
    }
}
