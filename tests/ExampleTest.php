<?php

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    public function testExample()
    {
        $this->assertTrue(true, 'This test ensures true is true.');
    }
}

class SampleTest extends TestCase
{
    public function testAddition()
    {
        $this->assertEquals(4, 2 + 2, 'This test ensures 2 + 2 equals 4.');
    }

    public function testStringContains()
    {
        $this->assertStringContainsString('world', 'Hello world', 'This test ensures "Hello world" contains "world".');
    }
}