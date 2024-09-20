<?php

namespace DirectoryTree\ActiveRedis;

use Illuminate\Support\Arr;
use RuntimeException;

class ModelNotFoundException extends RuntimeException
{
    /**
     * Name of the affected Eloquent model.
     */
    protected string $model;

    /**
     * The affected model IDs.
     */
    protected array $ids = [];

    /**
     * Set the affected Eloquent model and instance ids.
     */
    public function setModel(string $model, array|int|string $ids = [])
    {
        $this->model = $model;
        $this->ids = Arr::wrap($ids);

        $this->message = "No query results for model [{$model}]";

        if (count($this->ids) > 0) {
            $this->message .= ' '.implode(', ', $this->ids);
        } else {
            $this->message .= '.';
        }

        return $this;
    }

    /**
     * Get the affected Eloquent model.
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Get the affected Eloquent model IDs.
     */
    public function getIds(): array
    {
        return $this->ids;
    }
}
