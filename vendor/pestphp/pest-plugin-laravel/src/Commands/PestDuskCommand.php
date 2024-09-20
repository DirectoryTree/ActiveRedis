<?php

declare(strict_types=1);

namespace Pest\Laravel\Commands;

use Laravel\Dusk\Console\DuskCommand;

/**
 * @internal
 */
final class PestDuskCommand extends DuskCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'pest:dusk
                {--browse : Open a browser instead of using headless mode}
                {--without-tty : Disable output to TTY}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the Dusk tests for the application with Pest';

    /**
     * Get the PHP binary to execute.
     *
     * @return array<string>
     */
    protected function binary(): array
    {
        if ('phpdbg' === PHP_SAPI) {
            return [PHP_BINARY, '-qrr', 'vendor/pestphp/pest/bin/pest'];
        }

        return [PHP_BINARY, 'vendor/pestphp/pest/bin/pest'];
    }

    /**
     * Determine if Collision's printer should be used.
     *
     * @return bool
     */
    protected function shouldUseCollisionPrinter()
    {
        return false;
    }
}
