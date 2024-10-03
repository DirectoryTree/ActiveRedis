<?php

namespace DirectoryTree\ActiveRedis\Concerns;

use BackedEnum;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException as BrickMathException;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeImmutable;
use DateTimeInterface;
use DirectoryTree\ActiveRedis\Exceptions\JsonEncodingException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Exceptions\MathException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use InvalidArgumentException;
use UnitEnum;
use ValueError;

/** @mixin \DirectoryTree\ActiveRedis\Model */
trait HasCasts
{
    /**
     * The attributes that should be cast.
     */
    protected array $casts = [];

    /**
     * The cache of the converted cast types.
     */
    protected static array $castTypeCache = [];

    /**
     * The built-in, primitive cast types.
     */
    protected static array $primitiveCastTypes = [
        'array',
        'bool',
        'boolean',
        'collection',
        'custom_datetime',
        'date',
        'datetime',
        'decimal',
        'double',
        'float',
        'hashed',
        'immutable_date',
        'immutable_datetime',
        'immutable_custom_datetime',
        'int',
        'integer',
        'json',
        'object',
        'real',
        'string',
        'timestamp',
    ];

    /**
     * Get the attributes that should be cast.
     */
    public function getCasts(): array
    {
        return $this->casts;
    }

    /**
     * Determine whether an attribute should be cast to a native type.
     */
    public function hasCast(string $key, array|string|null $types = null): bool
    {
        if (array_key_exists($key, $this->getCasts())) {
            return $types ? in_array($this->getCastType($key), (array) $types, true) : true;
        }

        return false;
    }

    /**
     * Cast an attribute to a native PHP type.
     */
    protected function castAttribute(string $key, mixed $value): mixed
    {
        $castType = $this->getCastType($key);

        if (is_null($value) && in_array($castType, static::$primitiveCastTypes)) {
            return $value;
        }

        switch ($castType) {
            case 'int':
            case 'integer':
                return (int) $value;
            case 'real':
            case 'float':
            case 'double':
                return $this->fromFloat($value);
            case 'decimal':
                return $this->asDecimal($value, explode(':', $this->getCasts()[$key], 2)[1]);
            case 'string':
                return (string) $value;
            case 'bool':
            case 'boolean':
                return (bool) $value;
            case 'object':
                return $this->fromJson($value, true);
            case 'array':
            case 'json':
                return $this->fromJson($value);
            case 'collection':
                return new Collection($this->fromJson($value));
            case 'date':
                return $this->asDate($value);
            case 'datetime':
            case 'custom_datetime':
                return $this->asDateTime($value);
            case 'immutable_date':
                return $this->asDate($value)->toImmutable();
            case 'immutable_custom_datetime':
            case 'immutable_datetime':
                return $this->asDateTime($value)->toImmutable();
            case 'timestamp':
                return $this->asTimestamp($value);
        }

        if ($this->isEnumCastable($key)) {
            return $this->getEnumCastableAttributeValue($key, $value);
        }

        return $value;
    }

    /**
     * Get the type of cast for a model attribute.
     */
    protected function getCastType(string $key): string
    {
        $castType = $this->getCasts()[$key];

        if (isset(static::$castTypeCache[$castType])) {
            return static::$castTypeCache[$castType];
        }

        if ($this->isCustomDateTimeCast($castType)) {
            $convertedCastType = 'custom_datetime';
        } elseif ($this->isImmutableCustomDateTimeCast($castType)) {
            $convertedCastType = 'immutable_custom_datetime';
        } elseif ($this->isDecimalCast($castType)) {
            $convertedCastType = 'decimal';
        } else {
            $convertedCastType = trim(strtolower($castType));
        }

        return static::$castTypeCache[$castType] = $convertedCastType;
    }

    /**
     * Set the value of an enum castable attribute.
     *
     * @param  \UnitEnum|string|int|null  $value
     */
    protected function setEnumCastableAttribute(string $key, mixed $value): void
    {
        $enumClass = $this->getCasts()[$key];

        if (! isset($value)) {
            $this->attributes[$key] = null;
        } elseif (is_object($value)) {
            $this->attributes[$key] = $this->getStorableEnumValue($enumClass, $value);
        } else {
            $this->attributes[$key] = $this->getStorableEnumValue(
                $enumClass, $this->getEnumCaseFromValue($enumClass, $value)
            );
        }
    }

    /**
     * Get the storable value from the given enum.
     */
    protected function getStorableEnumValue(string $expectedEnum, UnitEnum|BackedEnum $value): string|int
    {
        if (! $value instanceof $expectedEnum) {
            throw new ValueError(sprintf('Value [%s] is not of the expected enum type [%s].', var_export($value, true), $expectedEnum));
        }

        return $value instanceof BackedEnum
            ? $value->value
            : $value->name;
    }

    /**
     * Determine if the cast type is a custom date time cast.
     */
    protected function isCustomDateTimeCast(string $cast): bool
    {
        return Str::startsWith($cast, ['date:', 'datetime:']);
    }

    /**
     * Determine if the cast type is an immutable custom date time cast.
     */
    protected function isImmutableCustomDateTimeCast(string $cast): bool
    {
        return Str::startsWith($cast, ['immutable_date:', 'immutable_datetime:']);
    }

    /**
     * Determine if the cast type is a decimal cast.
     */
    protected function isDecimalCast(string $cast): bool
    {
        return Str::startsWith($cast, 'decimal:');
    }

    /**
     * Determine whether a value is Date / DateTime castable for inbound manipulation.
     */
    protected function isDateCastable(string $key): bool
    {
        return $this->hasCast($key, ['date', 'datetime', 'immutable_date', 'immutable_datetime']);
    }

    /**
     * Determine whether a value is Date / DateTime custom-castable for inbound manipulation.
     */
    protected function isDateCastableWithCustomFormat(string $key): bool
    {
        return $this->hasCast($key, ['custom_datetime', 'immutable_custom_datetime']);
    }

    /**
     * Determine whether a value is JSON castable for inbound manipulation.
     */
    protected function isJsonCastable(string $key): bool
    {
        return $this->hasCast($key, ['array', 'json', 'object', 'collection']);
    }

    /**
     * Determine whether a value is boolean castable for inbound manipulation.
     */
    protected function isBooleanCastable(string $key): bool
    {
        return $this->hasCast($key, ['bool', 'boolean']);
    }

    /**
     * Determine if the given key is cast using an enum.
     */
    protected function isEnumCastable(string $key): bool
    {
        $casts = $this->getCasts();

        if (! array_key_exists($key, $casts)) {
            return false;
        }

        return enum_exists($casts[$key]);
    }

    /**
     * Decode the given float.
     */
    public function fromFloat(mixed $value): float
    {
        return match ((string) $value) {
            'Infinity' => INF,
            '-Infinity' => -INF,
            'NaN' => NAN,
            default => (float) $value,
        };
    }

    /**
     * Decode the given JSON back into an array or object.
     */
    public function fromJson(?string $value, bool $asObject = false): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return json_decode($value, ! $asObject);
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
     * Cast the given attribute to JSON.
     */
    protected function castAttributeAsJson(string $key, mixed $value): string
    {
        $value = $this->asJson($value);

        if ($value === false) {
            throw JsonEncodingException::forAttribute(
                $this, $key, json_last_error_msg()
            );
        }

        return $value;
    }

    /**
     * Return a decimal as string.
     */
    protected function asDecimal(float|string $value, int $decimals): string
    {
        try {
            return (string) BigDecimal::of($value)->toScale($decimals, RoundingMode::HALF_UP);
        } catch (BrickMathException $e) {
            throw new MathException('Unable to cast value to a decimal.', previous: $e);
        }
    }

    /**
     * Encode the given value as JSON.
     */
    protected function asJson(mixed $value): string|false
    {
        return json_encode($value);
    }

    /**
     * Return a timestamp as unix timestamp.
     */
    protected function asTimestamp(mixed $value): int
    {
        return $this->asDateTime($value)->getTimestamp();
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
     * Prepare a date for array / JSON serialization.
     */
    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date instanceof DateTimeImmutable
            ? CarbonImmutable::instance($date)->toJSON()
            : Carbon::instance($date)->toJSON();
    }

    /**
     * Determine if the given value is a standard date format.
     */
    protected function isStandardDateFormat(string $value): bool
    {
        return preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value);
    }

    /**
     * Cast the given attribute to an enum.
     */
    protected function getEnumCastableAttributeValue(string $key, mixed $value): mixed
    {
        if (is_null($value)) {
            return null;
        }

        $castType = $this->getCasts()[$key];

        if ($value instanceof $castType) {
            return $value;
        }

        return $this->getEnumCaseFromValue($castType, $value);
    }

    /**
     * Get an enum case instance from a given class and value.
     */
    protected function getEnumCaseFromValue(string $enumClass, string|int $value): UnitEnum|BackedEnum
    {
        return is_subclass_of($enumClass, BackedEnum::class)
            ? $enumClass::from($value)
            : constant($enumClass.'::'.$value);
    }
}
