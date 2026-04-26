<?php

namespace PHPUnit\Framework;

abstract class TestCase
{
    public function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            $this->fail($message ?: 'Failed asserting that two values are identical.');
        }
    }

    public function assertFalse(mixed $actual, string $message = ''): void
    {
        if ($actual !== false) {
            $this->fail($message ?: 'Failed asserting that value is false.');
        }
    }

    public function assertArrayNotHasKey(string|int $key, array $array, string $message = ''): void
    {
        if (array_key_exists($key, $array)) {
            $this->fail($message ?: "Failed asserting that array does not have key '{$key}'.");
        }
    }

    public function assertStringContainsString(string $needle, string $haystack, string $message = ''): void
    {
        if (!str_contains($haystack, $needle)) {
            $this->fail($message ?: "Failed asserting that string contains '{$needle}'.");
        }
    }

    protected function fail(string $message): never
    {
        throw new \RuntimeException($message);
    }
}
