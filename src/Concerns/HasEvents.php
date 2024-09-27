<?php

namespace DirectoryTree\ActiveRedis\Concerns;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Events\NullDispatcher;
use Illuminate\Support\Arr;
use InvalidArgumentException;

/** @mixin \DirectoryTree\ActiveRedis\Model */
trait HasEvents
{
    /**
     * The event dispatcher instance.
     */
    protected static ?Dispatcher $dispatcher = null;

    /**
     * Get the event dispatcher instance.
     */
    public static function getEventDispatcher(): ?Dispatcher
    {
        return static::$dispatcher;
    }

    /**
     * Set the event dispatcher instance.
     */
    public static function setEventDispatcher(Dispatcher $dispatcher): void
    {
        static::$dispatcher = $dispatcher;
    }

    /**
     * Unset the event dispatcher for models.
     */
    public static function unsetEventDispatcher(): void
    {
        static::$dispatcher = null;
    }

    /**
     * Execute a callback without firing any model event listeners for any model type.
     */
    public static function withoutEvents(callable $callback): mixed
    {
        $dispatcher = static::getEventDispatcher();

        if ($dispatcher) {
            static::setEventDispatcher(new NullDispatcher($dispatcher));
        }

        try {
            return $callback();
        } finally {
            if ($dispatcher) {
                static::setEventDispatcher($dispatcher);
            }
        }
    }

    /**
     * Remove all the event listeners for the model.
     */
    public static function flushEventListeners(): void
    {
        if (! isset(static::$dispatcher)) {
            return;
        }

        foreach ((new static)->getObservableEvents() as $event) {
            static::$dispatcher->forget("redis.model.{$event}: ".static::class);
        }
    }

    /**
     * Register a retrieved model event listener with the dispatcher.
     */
    public static function retrieved(mixed $callback): void
    {
        static::registerModelEvent('retrieved', $callback);
    }

    /**
     * Register a saving model event listener with the dispatcher.
     */
    public static function saving(mixed $callback): void
    {
        static::registerModelEvent('saving', $callback);
    }

    /**
     * Register a saved model event listener with the dispatcher.
     */
    public static function saved(mixed $callback): void
    {
        static::registerModelEvent('saved', $callback);
    }

    /**
     * Register an updating model event listener with the dispatcher.
     */
    public static function updating(mixed $callback): void
    {
        static::registerModelEvent('updating', $callback);
    }

    /**
     * Register an updated model event listener with the dispatcher.
     */
    public static function updated(mixed $callback): void
    {
        static::registerModelEvent('updated', $callback);
    }

    /**
     * Register a creating model event listener with the dispatcher.
     */
    public static function creating(mixed $callback): void
    {
        static::registerModelEvent('creating', $callback);
    }

    /**
     * Register a created model event listener with the dispatcher.
     */
    public static function created(mixed $callback): void
    {
        static::registerModelEvent('created', $callback);
    }

    /**
     * Register a replicating model event listener with the dispatcher.
     */
    public static function replicating(mixed $callback): void
    {
        static::registerModelEvent('replicating', $callback);
    }

    /**
     * Register a deleting model event listener with the dispatcher.
     */
    public static function deleting(mixed $callback): void
    {
        static::registerModelEvent('deleting', $callback);
    }

    /**
     * Register a deleted model event listener with the dispatcher.
     */
    public static function deleted(mixed $callback): void
    {
        static::registerModelEvent('deleted', $callback);
    }

    /**
     * Register observers with the model.
     */
    public static function observe(object|array|string $classes): void
    {
        $instance = new static;

        foreach (Arr::wrap($classes) as $class) {
            $instance->registerObserver($class);
        }
    }

    /**
     * Register a model event listener with the dispatcher.
     */
    protected static function registerModelEvent(string $event, mixed $callback): void
    {
        if (isset(static::$dispatcher)) {
            $name = static::class;

            static::$dispatcher->listen("redis.model.{$event}: {$name}", $callback);
        }
    }

    /**
     * Get the observable event names.
     */
    public function getObservableEvents(): array
    {
        return [
            'retrieved',
            'creating', 'created',
            'updating', 'updated',
            'saving', 'saved',
            'deleting', 'deleted',
        ];
    }

    /**
     * Register a single observer with the model.
     */
    protected function registerObserver(object|string $class): void
    {
        $className = $this->resolveObserverClassName($class);

        // When registering a model observer, we will spin through the
        // possible events and determine if this observer has that
        // method. If it does, we will register it with the model.
        foreach ($this->getObservableEvents() as $event) {
            if (method_exists($class, $event)) {
                static::registerModelEvent($event, $className.'@'.$event);
            }
        }
    }

    /**
     * Resolve the observer's class name from an object or string.
     */
    protected function resolveObserverClassName(object|string $class): string
    {
        if (is_object($class)) {
            return get_class($class);
        }

        if (class_exists($class)) {
            return $class;
        }

        throw new InvalidArgumentException('Unable to find observer: '.$class);
    }

    /**
     * Fire the given event for the model.
     */
    protected function fireModelEvent(string $event, bool $halt = true): mixed
    {
        if (! isset(static::$dispatcher)) {
            return true;
        }

        $method = $halt ? 'until' : 'dispatch';

        return static::$dispatcher->{$method}(
            "redis.model.{$event}: ".static::class, $this
        );
    }
}
