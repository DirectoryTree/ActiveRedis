<?php

namespace DirectoryTree\ActiveRedis;

use Illuminate\Support\Arr;
use RuntimeException;

class ModelNotFoundException extends RuntimeException
{
    /**
     * Class name of the affected model.
     */
    protected string $model;

    /**
     * The affected model keys.
     */
    protected array $keys = [];

    /**
     * Set the affected Eloquent model and instance ids.
     */
    public function setModel(string $model, array|int|string $keys = []): self
    {
        $this->model = $model;
        $this->keys = Arr::wrap($keys);

        $this->message = "No query results for model [{$model}]";

        if (count($this->keys) > 0) {
            $this->message .= ' '.implode(', ', $this->keys);
        } else {
            $this->message .= '.';
        }

        return $this;
    }

    /**
     * Get the affected model.
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Get the affected model keys.
     */
    public function getKeys(): array
    {
        return $this->keys;
    }
}
