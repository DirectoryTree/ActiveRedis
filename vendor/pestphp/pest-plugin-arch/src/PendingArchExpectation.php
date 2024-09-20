<?php

declare(strict_types=1);

namespace Pest\Arch;

use Closure;
use Pest\Arch\Contracts\ArchExpectation;
use Pest\Expectation;
use Pest\Expectations\HigherOrderExpectation;
use PHPUnit\Architecture\Elements\ObjectDescription;

/**
 * @internal
 *
 * @mixin Expectation<array|string>
 */
final class PendingArchExpectation
{
    /**
     * Whether the expectation is "opposite".
     */
    private bool $opposite = false;

    /**
     * Creates a new Pending Arch Expectation instance.
     *
     * @param  array<int, Closure(ObjectDescription): bool>  $excludeCallbacks
     */
    public function __construct(
        private readonly Expectation $expectation,
        private array $excludeCallbacks,
    ) {}

    /**
     * Filters the given "targets" by only classes.
     */
    public function classes(): self
    {
        $this->excludeCallbacks[] = fn (ObjectDescription $object): bool => ! class_exists($object->name) || enum_exists($object->name);

        return $this;
    }

    /**
     * Filters the given "targets" by only interfaces.
     */
    public function interfaces(): self
    {
        $this->excludeCallbacks[] = fn (ObjectDescription $object): bool => ! interface_exists($object->name);

        return $this;
    }

    /**
     * Filters the given "targets" by only traits.
     */
    public function traits(): self
    {
        $this->excludeCallbacks[] = fn (ObjectDescription $object): bool => ! trait_exists($object->name);

        return $this;
    }

    /**
     * Filters the given "targets" by only enums.
     */
    public function enums(): self
    {
        $this->excludeCallbacks[] = fn (ObjectDescription $object): bool => ! enum_exists($object->name);

        return $this;
    }

    /**
     * Creates an opposite expectation.
     */
    public function not(): self
    {
        $this->opposite = ! $this->opposite;

        return $this;
    }

    /**
     * Proxies the call to the expectation.
     *
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $name, array $arguments): ArchExpectation
    {
        $expectation = $this->opposite ? $this->expectation->not() : $this->expectation;

        /** @var $archExpectation SingleArchExpectation */
        $archExpectation = $expectation->{$name}(...$arguments); // @phpstan-ignore-line

        if ($archExpectation instanceof HigherOrderExpectation) {
            $originalExpectation = (fn (): \Pest\Expectation => $this->original)->call($archExpectation);
        } else {
            $originalExpectation = $archExpectation;
        }

        $originalExpectation->mergeExcludeCallbacks($this->excludeCallbacks);

        return $archExpectation;
    }

    /**
     * Proxies the call to the expectation.
     */
    public function __get(string $name): mixed
    {
        return $this->{$name}(); // @phpstan-ignore-line
    }
}
