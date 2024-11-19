<?php

namespace DirectoryTree\ActiveRedis\Repositories;

use Closure;
use Generator;
use Illuminate\Support\Str;

class ArrayRepository implements Repository
{
    /**
     * Constructor.
     */
    public function __construct(
        protected array $data = [],
        protected array $expiry = [],
    ) {}

    /**
     * Determine if the given hash exists.
     */
    public function exists(string $hash): bool
    {
        return isset($this->data[$hash]);
    }

    /**
     * Chunk through the hashes matching the given pattern.
     */
    public function chunk(string $pattern, int $count): Generator
    {
        $matches = [];

        foreach (array_keys($this->data) as $key) {
            if (Str::is($pattern, $key)) {
                $matches[] = $key;
            }
        }

        foreach (array_chunk($matches, $count) as $chunk) {
            yield $chunk;
        }
    }

    /**
     * Set the hash field's value.
     */
    public function setAttribute(string $hash, string $attribute, string $value): void
    {
        $this->data[$hash][$attribute] = $value;
    }

    /**
     * Set the hash field's value.
     */
    public function setAttributes(string $hash, array $attributes): void
    {
        foreach ($attributes as $attribute => $value) {
            $this->setAttribute($hash, $attribute, $value);
        }
    }

    /**
     * Get the hash field's value.
     */
    public function getAttribute(string $hash, string $field): mixed
    {
        if ($this->isExpired($hash)) {
            $this->delete($hash);

            return null;
        }

        return $this->data[$hash][$field] ?? null;
    }

    /**
     * Get all the attributes in the hash.
     */
    public function getAttributes(string $hash): array
    {
        if ($this->isExpired($hash)) {
            $this->delete($hash);

            return [];
        }

        return $this->data[$hash] ?? [];
    }

    /**
     * Set a time-to-live on a hash key.
     *
     * Not supported in ArrayRepository.
     */
    public function setExpiry(string $hash, int $seconds): void
    {
        $this->expiry[$hash] = time() + $seconds;
    }

    /**
     * Get the time-to-live of a hash key.
     */
    public function getExpiry(string $hash): ?int
    {
        if (! isset($this->expiry[$hash])) {
            return null;
        }

        $remaining = $this->expiry[$hash] - time();

        return $remaining > 0 ? $remaining : null;
    }

    /**
     * Delete the attributes from the hash.
     */
    public function deleteAttributes(string $hash, array|string $attributes): void
    {
        foreach ((array) $attributes as $attribute) {
            unset($this->data[$hash][$attribute]);
        }
    }

    /**
     * Delete the given hash.
     */
    public function delete(string $hash): void
    {
        unset($this->data[$hash]);
    }

    /**
     * Perform a transaction.
     */
    public function transaction(Closure $operation): void
    {
        $operation($this);
    }

    /**
     * Determine if the given hash has expired.
     */
    protected function isExpired(string $hash): bool
    {
        return isset($this->expiry[$hash]) && $this->expiry[$hash] <= time();
    }
}
