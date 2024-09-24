<?php

namespace DirectoryTree\ActiveRedis\Repositories;

use Closure;
use Generator;
use Illuminate\Contracts\Redis\Connection;

class RedisRepository implements Repository
{
    /**
     * Constructor.
     */
    public function __construct(
        protected Connection $redis
    ) {}

    /**
     * Determine if the given hash exists.
     */
    public function exists(string $hash): bool
    {
        return $this->redis->exists($hash);
    }

    /**
     * Chunk through the hashes matching the given pattern.
     */
    public function chunk(string $pattern, int $count): Generator
    {
        $cursor = null;

        do {
            [$cursor, $keys] = $this->redis->scan($cursor, [
                'match' => $pattern,
                'count' => $count,
            ]);

            yield $keys;
        } while ($cursor !== '0');
    }

    /**
     * Set the hash field's value.
     */
    public function setAttribute(string $hash, string $field, mixed $value): void
    {
        $this->redis->hset($hash, $field, $value);
    }

    /**
     * Get the hash field's value.
     */
    public function getAttribute(string $hash, string $field): mixed
    {
        return $this->redis->hget($hash, $field);
    }

    /**
     * Get all the fields in the hash.
     */
    public function getAttributes(string $hash): array
    {
        return $this->redis->hgetall($hash);
    }

    /**
     * Set a time-to-live on a hash key.
     */
    public function setExpiry(string $hash, int $seconds): void
    {
        $this->redis->expire($hash, $seconds);
    }

    /**
     * Get the time-to-live of a hash key.
     */
    public function getExpiry(string $hash): ?int
    {
        // The number of seconds until the key will expire, or
        // null if the key does not exist or has no timeout.
        return $this->redis->ttl($hash);
    }

    /**
     * Delete the field from the hash.
     */
    public function deleteAttribute(string $hash, string $field): void
    {
        $this->redis->hdel($hash, $field);
    }

    /**
     * Delete the given hash.
     */
    public function delete(string $hash): void
    {
        $this->redis->del($hash);
    }

    /**
     * Perform a Redis transaction.
     */
    public function transaction(Closure $operation): void
    {
        $this->redis->transaction(
            fn () => $operation($this)
        );
    }
}
