<?php

namespace DirectoryTree\ActiveRedis\Repositories;

use Closure;
use Generator;
use Illuminate\Contracts\Redis\Connection;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Redis\Connections\PredisConnection;
use Illuminate\Support\Str;

class ClusterRedisRepository implements Repository
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
     *
     * In cluster mode, this method scans all nodes and aggregates results.
     * For regular Redis, it works identically to RedisRepository.
     */
    public function chunk(string $pattern, int $count): Generator
    {
        [$cursor, $prefix] = $this->getScanParameters();

        do {
            [$cursor, $keys] = $this->redis->scan($cursor, [
                'match' => $prefix.$pattern,
                'count' => $count,
            ]);

            if (is_null($keys)) {
                return;
            }

            if (empty($keys)) {
                continue;
            }

            if (empty($prefix)) {
                yield $keys;

                continue;
            }

            yield array_map(function (string $key) use ($prefix) {
                return Str::after($key, $prefix);
            }, $keys);
        } while ($cursor !== '0');
    }

    /**
     * Get the scan parameters for the Redis connection.
     */
    protected function getScanParameters(): array
    {
        return match (true) {
            $this->redis instanceof PhpRedisConnection => [
                null,
                $this->redis->getOption($this->redis->client()::OPT_PREFIX) ?? '',
            ],
            $this->redis instanceof PredisConnection => [
                0,
                $this->redis->getOptions()->prefix->getPrefix() ?? '',
            ],
            default => [
                null,
                '',
            ]
        };
    }

    /**
     * Set the hash field's value.
     */
    public function setAttribute(string $hash, string $attribute, string $value): void
    {
        $this->redis->hset($hash, $attribute, $value);
    }

    /**
     * Set multiple hash fields' values.
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
        $ttl = $this->redis->ttl($hash);

        return $ttl > 0 ? $ttl : null;
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
        $operation($this);
    }
}
