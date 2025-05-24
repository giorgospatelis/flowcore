<?php

declare(strict_types=1);

use FlowCore\Job\AbstractJob;
use FlowCore\Job\JobInterface;
use FlowCore\Job\Registry;
use PHPUnit\Framework\TestCase;

final class DummyJob extends AbstractJob
{
    public array $payload = [];

    public function run(array $payload): void
    {
        $this->payload = $payload;
    }
}

final class RegistryTest extends TestCase
{
    public function test_register_and_resolve_job(): void
    {
        $registry = new Registry();
        $registry->register('dummy', DummyJob::class);

        $job = $registry->resolve('dummy');
        $this->assertInstanceOf(JobInterface::class, $job);

        $payload = ['foo' => 'bar'];
        $job->run($payload);
        $this->assertSame($payload, $job->payload);
    }

    public function test_resolve_nonexistent_job_throws(): void
    {
        $this->expectException(RuntimeException::class);
        $registry = new Registry();
        $registry->resolve('notfound');
    }
}
