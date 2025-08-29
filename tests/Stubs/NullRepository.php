<?php

namespace DirectoryTree\ActiveRedis\Tests\Stubs;

use Closure;
use DirectoryTree\ActiveRedis\Repositories\Repository;
use Generator;

class NullRepository implements Repository
{
    public function exists(string $hash): bool
    {
        return false;
    }

    public function chunk(string $pattern, int $count): Generator
    {
        yield [];
    }

    public function setAttribute(string $hash, string $attribute, string $value): void
    {
        // Do nothing.
    }

    public function setAttributes(string $hash, array $attributes): void
    {
        // Do nothing.
    }

    public function getAttribute(string $hash, string $field): mixed
    {
        return null;
    }

    public function getAttributes(string $hash): array
    {
        return [];
    }

    public function setExpiry(string $hash, int $seconds): void
    {
        // Do nothing.
    }

    public function getExpiry(string $hash): ?int
    {
        return null;
    }

    public function deleteAttributes(string $hash, array|string $attributes): void
    {
        // Do nothing.
    }

    public function delete(string $hash): void
    {
        // Do nothing.
    }

    public function transaction(Closure $operation): void
    {
        // Do nothing.
    }
}
