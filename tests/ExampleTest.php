<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ExampleTest extends TestCase
{
    public function test_example(): void
    {
        $this->assertTrue(true, 'This test ensures true is true.');
    }
}

final class SampleTest extends TestCase
{
    public function test_addition(): void
    {
        $this->assertEquals(4, 2 + 2, 'This test ensures 2 + 2 equals 4.');
    }

    public function test_string_contains(): void
    {
        $this->assertStringContainsString('world', 'Hello world', 'This test ensures "Hello world" contains "world".');
    }
}
