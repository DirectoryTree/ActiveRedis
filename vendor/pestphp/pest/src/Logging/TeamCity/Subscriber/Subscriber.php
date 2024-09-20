<?php

declare(strict_types=1);

namespace Pest\Logging\TeamCity\Subscriber;

use Pest\Logging\TeamCity\TeamCityLogger;

/**
 * @internal
 */
abstract class Subscriber // @pest-arch-ignore-line
{
    /**
     * Creates a new Subscriber instance.
     */
    public function __construct(private readonly TeamCityLogger $logger) {}

    /**
     * Creates a new TeamCityLogger instance.
     */
    final protected function logger(): TeamCityLogger // @pest-arch-ignore-line
    {
        return $this->logger;
    }
}
