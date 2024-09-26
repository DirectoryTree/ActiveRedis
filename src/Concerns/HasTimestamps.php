<?php

namespace DirectoryTree\ActiveRedis\Concerns;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;

/** @mixin \DirectoryTree\ActiveRedis\Model */
trait HasTimestamps
{
    /**
     * Indicates if the model should be timestamped.
     */
    public bool $timestamps = true;

    /**
     * Update the model's update timestamp.
     */
    public function touch(?string $attribute = null): void
    {
        if ($attribute) {
            $this->$attribute = $this->freshTimestamp();

            $this->save();

            return;
        }

        if (! $this->usesTimestamps()) {
            return;
        }

        $this->updateTimestamps();

        $this->save();
    }

    /**
     * Update the creation and update timestamps.
     */
    public function updateTimestamps(): self
    {
        $time = $this->freshTimestamp();

        $updatedAtColumn = $this->getUpdatedAtColumn();

        if (! is_null($updatedAtColumn) && ! $this->isDirty($updatedAtColumn)) {
            $this->setUpdatedAt($time);
        }

        $createdAtColumn = $this->getCreatedAtColumn();

        if (! $this->exists && ! is_null($createdAtColumn) && ! $this->isDirty($createdAtColumn)) {
            $this->setCreatedAt($time);
        }

        return $this;
    }

    /**
     * Set the value of the "created at" attribute.
     */
    public function setCreatedAt(mixed $value): self
    {
        $this->{$this->getCreatedAtColumn()} = $value;

        return $this;
    }

    /**
     * Set the value of the "updated at" attribute.
     */
    public function setUpdatedAt(mixed $value): self
    {
        $this->{$this->getUpdatedAtColumn()} = $value;

        return $this;
    }

    /**
     * Get a fresh timestamp for the model.
     */
    public function freshTimestamp(): CarbonInterface
    {
        return Date::now();
    }

    /**
     * Get a fresh timestamp for the model.
     */
    public function freshTimestampString(): string
    {
        return $this->fromDateTime($this->freshTimestamp());
    }

    /**
     * Determine if the model uses timestamps.
     */
    public function usesTimestamps(): bool
    {
        return $this->timestamps;
    }

    /**
     * Get the name of the "created at" column.
     */
    public function getCreatedAtColumn(): ?string
    {
        return static::CREATED_AT;
    }

    /**
     * Get the name of the "updated at" column.
     */
    public function getUpdatedAtColumn(): ?string
    {
        return static::UPDATED_AT;
    }
}
