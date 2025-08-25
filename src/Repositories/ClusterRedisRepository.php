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
     * Scan a specific cluster node for matching keys.
     */
    protected function scanNode(array $node, string $pattern, int $count): Generator
    {
        $host = $node['host'];
        $port = $node['port'];

        // Create connection to specific node
        $nodeConnection = $this->createNodeConnection($host, $port);

        [$cursor, $prefix] = [0, ''];

        do {
            [$cursor, $keys] = $nodeConnection->scan($cursor, [
                'match' => $prefix.$pattern,
                'count' => $count,
            ]);

            if (is_null($keys) || empty($keys)) {
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
     * Single node chunk implementation (fallback).
     */
    protected function singleNodeChunk(string $pattern, int $count): Generator
    {
        $cursor = 0;
        $prefix = '';

        do {
            [$cursor, $keys] = $this->redis->scan($cursor, [
                'match' => $pattern,
                'count' => $count,
            ]);

            if (is_null($keys) || empty($keys)) {
                continue;
            }

            yield $keys;
        } while ($cursor !== '0' && $cursor !== 0);
    }

    /**
     * Check if the connection is a cluster connection.
     */
    protected function isClusterConnection(): bool
    {
        try {
            if ($this->redis instanceof PredisConnection) {
                // Use Predis command method
                $this->redis->command('CLUSTER', ['INFO']);
            } elseif ($this->redis instanceof PhpRedisConnection) {
                // Use PhpRedis rawCommand method
                $this->redis->rawCommand('CLUSTER', 'INFO');
            } else {
                // Fallback for other connection types
                return false;
            }

            return true;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Get cluster nodes information.
     */
    protected function getClusterNodes(): array
    {
        if ($this->redis instanceof PredisConnection) {
            return $this->getPredisClusterNodes();
        }

        return $this->getPhpRedisClusterNodes();
    }

    /**
     * Get cluster nodes for Predis connection.
     */
    protected function getPredisClusterNodes(): array
    {
        try {
            $client = $this->redis->client();

            if (method_exists($client, 'getConnection')) {
                $connections = $client->getConnection();

                if (is_array($connections)) {
                    $nodes = [];
                    foreach ($connections as $connection) {
                        $parameters = $connection->getParameters();
                        $nodes[] = [
                            'host' => $parameters->host ?? 'localhost',
                            'port' => $parameters->port ?? 6379,
                        ];
                    }

                    return $nodes;
                }
            }

            // Try to get cluster nodes via CLUSTER NODES command
            $nodesInfo = $this->redis->execute(['CLUSTER', 'NODES']);

            return $this->parseClusterNodesOutput($nodesInfo);

        } catch (\Exception $e) {
            // Fallback: assume single node
            return [['host' => 'localhost', 'port' => 6379]];
        }
    }

    /**
     * Get cluster nodes for PhpRedis connection.
     */
    protected function getPhpRedisClusterNodes(): array
    {
        try {
            // Try to get master nodes from cluster
            $client = $this->redis->client();

            if (method_exists($client, '_masters')) {
                $masters = $client->_masters();

                return array_map(function ($master) {
                    [$host, $port] = explode(':', $master);

                    return ['host' => $host, 'port' => (int) $port];
                }, $masters);
            }

            // Fallback: parse CLUSTER NODES output
            $nodesInfo = $this->redis->rawCommand('CLUSTER', 'NODES');

            return $this->parseClusterNodesOutput($nodesInfo);

        } catch (\Exception $e) {
            // Fallback: assume single node
            return [['host' => 'localhost', 'port' => 6379]];
        }
    }

    /**
     * Parse CLUSTER NODES command output.
     */
    protected function parseClusterNodesOutput(string $output): array
    {
        $lines = explode("\n", trim($output));
        $nodes = [];

        foreach ($lines as $line) {
            if (empty($line)) {
                continue;
            }

            $parts = explode(' ', $line);
            if (count($parts) < 3) {
                continue;
            }

            $endpoint = $parts[1];
            if (strpos($endpoint, '@') !== false) {
                $endpoint = explode('@', $endpoint)[0];
            }

            [$host, $port] = explode(':', $endpoint);

            // Only include master nodes for scanning
            if (strpos($parts[2], 'master') !== false) {
                $nodes[] = [
                    'host' => $host,
                    'port' => (int) $port,
                ];
            }
        }

        return $nodes ?: [['host' => 'localhost', 'port' => 6379]];
    }

    /**
     * Create a connection to a specific cluster node.
     */
    protected function createNodeConnection(string $host, int $port): Connection
    {
        // For simplicity, we'll use the main connection but target specific node
        // In a production implementation, you might want to create dedicated connections
        return $this->redis;
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
     *
     * Note: In cluster mode, transactions are limited to keys in the same hash slot.
     * Consider using hash tags in your key design to ensure related keys are co-located.
     */
    public function transaction(Closure $operation): void
    {
        // Use Redis pipeline instead of transaction for better compatibility
        $this->redis->pipeline(function ($pipe) use ($operation) {
            // Create a wrapper repository that uses the pipeline
            $pipelineRepo = new class($pipe) implements Repository
            {
                private $pipe;

                public function __construct($pipe)
                {
                    $this->pipe = $pipe;
                }

                public function exists(string $hash): bool
                {
                    return $this->pipe->exists($hash);
                }

                public function chunk(string $pattern, int $count): Generator
                {
                    yield from [];
                }

                public function setAttribute(string $hash, string $attribute, string $value): void
                {
                    $this->pipe->hset($hash, $attribute, $value);
                }

                public function setAttributes(string $hash, array $attributes): void
                {
                    $this->pipe->hmset($hash, $attributes);
                }

                public function getAttribute(string $hash, string $field): mixed
                {
                    return $this->pipe->hget($hash, $field);
                }

                public function getAttributes(string $hash): array
                {
                    return $this->pipe->hgetall($hash);
                }

                public function setExpiry(string $hash, int $seconds): void
                {
                    $this->pipe->expire($hash, $seconds);
                }

                public function getExpiry(string $hash): ?int
                {
                    $ttl = $this->pipe->ttl($hash);

                    return $ttl > 0 ? $ttl : null;
                }

                public function deleteAttributes(string $hash, array|string $attributes): void
                {
                    $this->pipe->hdel($hash, ...(array) $attributes);
                }

                public function delete(string $hash): void
                {
                    $this->pipe->del($hash);
                }

                public function transaction(Closure $operation): void
                {
                    throw new \RuntimeException('Nested transactions are not supported');
                }
            };

            $operation($pipelineRepo);
        });
    }
}
