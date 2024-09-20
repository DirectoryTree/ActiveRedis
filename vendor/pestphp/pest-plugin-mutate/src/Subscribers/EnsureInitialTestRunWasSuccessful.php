<?php

declare(strict_types=1);

namespace Pest\Mutate\Subscribers;

use Pest\Mutate\Repositories\TelemetryRepository;
use Pest\Support\Container;
use PHPUnit\Event\Application\Finished;
use PHPUnit\Event\Application\FinishedSubscriber;

/**
 * @internal
 */
final class EnsureInitialTestRunWasSuccessful implements FinishedSubscriber
{
    public function notify(Finished $event): void
    {
        // @phpstan-ignore-next-line
        Container::getInstance()->get(TelemetryRepository::class)->initialTestSuiteDuration(
            $event->telemetryInfo()->durationSinceStart()
        );
    }
}
