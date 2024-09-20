<?php

namespace DirectoryTree\ActiveRedis;

use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use InvalidArgumentException;

/** @mixin Model */
trait HasAttributes
{
    /**
     * The model's attributes.
     */
    protected array $attributes = [];

    /**
     * The model attribute's original state.
     */
    protected array $original = [];

    /**
     * The changed model attributes.
     */
    protected array $changes = [];

    /**
     * Get the model's attributes.
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Get an attribute from the model.
     */
    public function getAttribute(string $key): mixed
    {
        $value = $this->attributes[$key] ?? null;

        if ($value !== null && in_array($key, $this->getDates())) {
            return $this->asDateTime($value);
        }

        return $value;
    }

    /**
     * Determine whether an attribute exists on the model.
     */
    public function hasAttribute(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    /**
     * Get a model's original attribute value.
     */
    public function getOriginal(?string $key = null, mixed $default = null): mixed
    {
        if (func_num_args() > 0) {
            return $this->original[$key] ?? $default;
        }

        return $this->original;
    }

    /**
     * Set a given attribute on the model.
     */
    public function setAttribute(string $key, mixed $value): self
    {
        if (! is_null($value) && $this->isDateAttribute($key)) {
            $value = $this->fromDateTime($value);
        }

        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * Set the model's attributes.
     */
    public function setAttributes(array $attributes, bool $sync = false): void
    {
        $this->attributes = $attributes;

        if ($sync) {
            $this->syncOriginal();
        }
    }

    /**
     * Sync the original attributes with the current.
     */
    public function syncOriginal(): self
    {
        $this->original = $this->getAttributes();

        return $this;
    }

    /**
     * Sync the changed attributes.
     */
    public function syncChanges(): self
    {
        $this->changes = $this->getDirty();

        return $this;
    }

    /**
     * Determine if the model or any of the given attribute(s) have been modified.
     */
    public function isDirty(array|string|null $attributes = null): bool
    {
        return $this->hasChanges(
            $this->getDirty(), is_array($attributes) ? $attributes : func_get_args()
        );
    }

    /**
     * Determine if any of the given attributes were changed when the model was last saved.
     */
    protected function hasChanges(array $changes, array|string|null $attributes = null): bool
    {
        if (empty($attributes)) {
            return count($changes) > 0;
        }

        foreach (Arr::wrap($attributes) as $attribute) {
            if (array_key_exists($attribute, $changes)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the attributes that have been changed since the last sync.
     */
    public function getDirty(): array
    {
        $dirty = [];

        foreach ($this->getAttributes() as $key => $value) {
            if (! $this->originalIsEquivalent($key)) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    /**
     * Get the attributes that were changed when the model was last saved.
     */
    public function getChanges(): array
    {
        return $this->changes;
    }

    /**
     * Determine if the new and old values for a given key are equivalent.
     */
    public function originalIsEquivalent(string $key): bool
    {
        if (! array_key_exists($key, $this->original)) {
            return false;
        }

        $attribute = Arr::get($this->attributes, $key);
        $original = Arr::get($this->original, $key);

        if ($attribute === $original) {
            return true;
        } elseif (is_null($attribute)) {
            return false;
        } elseif ($this->isDateAttribute($key)) {
            return $this->fromDateTime($attribute) === $this->fromDateTime($original);
        }

        return is_numeric($attribute) && is_numeric($original)
            && strcmp((string) $attribute, (string) $original) === 0;
    }

    /**
     * Return a timestamp as DateTime object with time set to 00:00:00.
     */
    protected function asDate(mixed $value): Carbon
    {
        return $this->asDateTime($value)->startOfDay();
    }

    /**
     * Return a timestamp as DateTime object.
     */
    protected function asDateTime(mixed $value): Carbon
    {
        if ($value instanceof CarbonInterface) {
            return Date::instance($value);
        }

        if ($value instanceof DateTimeInterface) {
            return Date::parse(
                $value->format('Y-m-d H:i:s.u'), $value->getTimezone()
            );
        }

        if (is_numeric($value)) {
            return Date::createFromTimestamp($value, date_default_timezone_get());
        }

        if ($this->isStandardDateFormat($value)) {
            return Date::instance(Carbon::createFromFormat('Y-m-d', $value)->startOfDay());
        }

        $format = $this->getDateFormat();

        try {
            $date = Date::createFromFormat($format, $value);
        } catch (InvalidArgumentException) {
            $date = false;
        }

        return $date ?: Date::parse($value);
    }

    /**
     * Determine if the given value is a standard date format.
     */
    protected function isStandardDateFormat(string $value): bool
    {
        return preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value);
    }

    /**
     * Determine if the given attribute is a date.
     */
    protected function isDateAttribute(string $key): bool
    {
        return in_array($key, $this->getDates(), true);
    }

    /**
     * Convert a DateTime to a storable string.
     */
    protected function fromDateTime(mixed $value): ?string
    {
        return empty($value) ? $value : $this->asDateTime($value)->format(
            $this->getDateFormat()
        );
    }

    /**
     * Get the format for database stored dates.
     */
    public function getDateFormat(): string
    {
        return $this->dateFormat;
    }

    /**
     * Get the attributes that should be converted to dates.
     */
    public function getDates(): array
    {
        return $this->usesTimestamps() ? [
            $this->getCreatedAtColumn(),
            $this->getUpdatedAtColumn(),
        ] : [];
    }

    /**
     * Return a timestamp as unix timestamp.
     */
    protected function asTimestamp(mixed $value): int
    {
        return $this->asDateTime($value)->getTimestamp();
    }
}
