<?php

declare(strict_types=1);

namespace Pest\Mutate\Options;

use Symfony\Component\Console\Input\InputOption;

class CoveredOnlyOption
{
    final public const ARGUMENT = 'covered-only';

    public static function remove(): bool
    {
        return true;
    }

    public static function match(string $argument): bool
    {
        return $argument === sprintf('--%s', self::ARGUMENT) ||
            str_starts_with($argument, sprintf('--%s=', self::ARGUMENT));
    }

    public static function inputOption(): InputOption
    {
        return new InputOption(sprintf('--%s', self::ARGUMENT), null, InputOption::VALUE_OPTIONAL, '');
    }
}
