<?php

namespace Orchestra\Canvas\Console;

use Orchestra\Canvas\Core\Concerns\MigrationGenerator;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * @see https://github.com/laravel/framework/blob/11.x/src/Illuminate/Queue/Console/FailedTableCommand.php
 */
#[AsCommand(name: 'make:queue-failed-table', description: 'Create a migration for the failed queue jobs database table', aliases: ['queue:failed-table'])]
class FailedTableCommand extends \Illuminate\Queue\Console\FailedTableCommand
{
    use MigrationGenerator;

    /**
     * Configures the current command.
     *
     * @return void
     */
    #[\Override]
    protected function configure()
    {
        parent::configure();

        $this->addGeneratorPresetOptions();
    }

    /**
     * Create a base migration file for the table.
     *
     * @param  string  $table
     * @return string
     */
    #[\Override]
    protected function createBaseMigration($table)
    {
        return $this->createBaseMigrationUsingCanvas($table);
    }

    /**
     * Determine whether a migration for the table already exists.
     *
     * @param  string  $table
     * @return bool
     */
    #[\Override]
    protected function migrationExists($table)
    {
        return $this->migrationExistsUsingCanvas($table);
    }
}
