<?php

declare(strict_types=1);

namespace Pest\Mutate\Repositories;

use PHPUnit\Event\Telemetry\Duration;

class TelemetryRepository
{
    private Duration $initialTestSuiteDuration;

    public function initialTestSuiteDuration(Duration $duration): void
    {
        $this->initialTestSuiteDuration = $duration;
    }

    public function getInitialTestSuiteDuration(): Duration
    {
        return $this->initialTestSuiteDuration;
    }
}
