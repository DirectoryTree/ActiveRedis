<?php

namespace DirectoryTree\ActiveRedis\Exceptions;

use RuntimeException;

class JsonEncodingException extends RuntimeException
{
    /**
     * Create a new JSON encoding exception for an attribute.
     */
    public static function forAttribute(mixed $model, string $key, string $message): static
    {
        return new static('Unable to encode attribute ['.$key.'] for model ['.get_class($model).'] to JSON: '.$message);
    }

    /**
     * Create a new JSON encoding exception for the model.
     */
    public static function forModel(mixed $model, string $message): static
    {
        return new static('Error encoding model ['.get_class($model).'] with ID ['.$model->getKey().'] to JSON: '.$message);
    }
}
