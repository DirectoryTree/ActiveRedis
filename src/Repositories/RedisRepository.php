<?php

namespace DirectoryTree\ActiveRedis\Repositories;

use Closure;
use Generator;
use Illuminate\Contracts\Redis\Connection;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Redis\Connections\PredisConnection;
use Illuminate\Support\Str;

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

        $prefix = match (true) {
            $this->redis instanceof PhpRedisConnection => $this->redis->getOption($this->redis->client()::OPT_PREFIX),
            $this->redis instanceof PredisConnection => $this->redis->getOptions()->prefix->getPrefix(),
            default => null
        };

        if (filled($prefix)) {
            $pattern = "{$prefix}{$pattern}";
        }

        do {
            [$cursor, $keys] = $this->redis->scan($cursor, [
                'match' => $pattern,
                'count' => $count,
            ]);

            if (is_null($keys)) {
                return;
            }

            if (empty($keys)) {
                continue;
            }

            if (filled($prefix)) {
                $keys = array_map(
                    fn (string $key) => Str::after($key, $prefix),
                    $keys
                );
            }

            yield $keys;
        } while ($cursor !== '0');
    }

    /**
     * Set the hash field's value.
     */
    public function setAttribute(string $hash, string $attribute, string $value): void
    {
        $this->redis->hset($hash, $attribute, $value);
    }

    /**
     * Set the hash field's value.
     */
    public function setAttributes(string $hash, array $attributes): void
    {
        $this->redis->hmset($hash, $attributes);
    }

    /**
     * Get the hash field's value.
     */
    public function getAttribute(string $hash, string $field): mixed
    {
        return $this->redis->hget($hash, $field);
    }

    /**
     * Get all the attributes in the hash.
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
     * Delete the attributes from the hash.
     */
    public function deleteAttributes(string $hash, array|string $attributes): void
    {
        $this->redis->hdel($hash, ...(array) $attributes);
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
