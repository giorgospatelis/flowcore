<?php

declare(strict_types=1);

namespace Tests\Unit\Contracts;

use FlowCore\Contracts\JobPayload;
use PHPUnit\Framework\TestCase;
use FlowCore\Contracts\JobPayloadInterface;

class JobPayloadTest extends TestCase
{
    private JobPayload $jobPayload;

    protected function setUp(): void
    {
        $this->jobPayload = new JobPayload([
            'test' => 'data'], ['priority' => 1]);
    }

    public function test_can_set_and_get_id(): void
    {
        $this->jobPayload->setId('12345');
        $this->assertSame('12345', $this->jobPayload->getId());
    }
}
