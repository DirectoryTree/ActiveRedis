<?php

namespace DirectoryTree\ActiveRedis\Repositories;

use Closure;
use Generator;

interface Repository
{
    /**
     * Determine if the given hash exists.
     */
    public function exists(string $hash): bool;

    /**
     * Chunk through the hashes matching the given pattern.
     */
    public function chunk(string $pattern, int $count): Generator;

    /**
     * Set a hash field's value.
     */
    public function setAttribute(string $hash, string $attribute, string $value): void;

    /**
     * Set multiple fields on the hash.
     */
    public function setAttributes(string $hash, array $attributes): void;

    /**
     * Get the hash field's value.
     */
    public function getAttribute(string $hash, string $field): mixed;

    /**
     * Get all the fields in the hash.
     */
    public function getAttributes(string $hash): array;

    /**
     * Get all the fields for each of the given hashes.
     *
     * Returns an array keyed by hash, each containing the hash's fields.
     */
    public function getManyAttributes(array $hashes): array;

    /**
     * Set a time-to-live on a hash key.
     */
    public function setExpiry(string $hash, int $seconds): void;

    /**
     * Get the time-to-live of a hash key.
     */
    public function getExpiry(string $hash): ?int;

    /**
     * Delete the attributes from the hash.
     */
    public function deleteAttributes(string $hash, array|string $attributes): void;

    /**
     * Delete the given hash.
     */
    public function delete(string $hash): void;

    /**
     * Perform a Redis transaction.
     */
    public function transaction(Closure $operation): void;
}
