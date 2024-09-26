<?php

namespace DirectoryTree\ActiveRedis\Concerns;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Events\NullDispatcher;

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
