<?php

namespace DirectoryTree\ActiveRedis\Exceptions;

use RuntimeException;

class JsonEncodingException extends RuntimeException
{
    /**
     * Create a new JSON encoding exception for an attribute.
     *
     * @param  mixed  $model
     * @param  mixed  $key
     * @return static
     */
    public static function forAttribute(string $model, string $key, string $message)
    {
        $class = get_class($model);

        return new static("Unable to encode attribute [{$key}] for model [{$class}] to JSON: {$message}.");
    }
}
