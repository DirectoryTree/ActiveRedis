<?php

namespace Orchestra\Workbench\Console;

use Composer\InstalledVersions;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Composer;
use Orchestra\Testbench\Foundation\Console\Actions\EnsureDirectoryExists;
use Orchestra\Testbench\Foundation\Console\Actions\GeneratesFile;
use Orchestra\Workbench\Events\InstallEnded;
use Orchestra\Workbench\Events\InstallStarted;
use Orchestra\Workbench\Workbench;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Illuminate\Filesystem\join_paths;
use function Laravel\Prompts\confirm;
use function Orchestra\Testbench\package_path;

#[AsCommand(name: 'workbench:devtool', description: 'Configure Workbench for package development')]
class DevToolCommand extends Command implements PromptsForMissingInput
{
    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(Filesystem $filesystem)
    {
        $workingPath = package_path();

        event(new InstallStarted($this->input, $this->output, $this->components));

        $this->prepareWorkbenchDirectories($filesystem, $workingPath);
        $this->prepareWorkbenchNamespaces($filesystem, $workingPath);

        if ($this->option('install') === true) {
            $this->call('workbench:install', [
                '--force' => $this->option('force'),
                '--no-devtool' => true,
            ]);
        }

        return tap(Command::SUCCESS, function ($exitCode) use ($filesystem, $workingPath) {
            event(new InstallEnded($this->input, $this->output, $this->components, $exitCode));

            (new Composer($filesystem))
                ->setWorkingPath($workingPath)
                ->dumpAutoloads();
        });
    }

    /**
     * Prepare workbench directories.
     */
    protected function prepareWorkbenchDirectories(Filesystem $filesystem, string $workingPath): void
    {
        $workbenchWorkingPath = join_paths($workingPath, 'workbench');

        (new EnsureDirectoryExists(
            filesystem: $filesystem,
            components: $this->components,
        ))->handle(
            Collection::make([
                join_paths('app', 'Models'),
                'bootstrap',
                'routes',
                join_paths('resources', 'views'),
                join_paths('database', 'factories'),
                join_paths('database', 'migrations'),
                join_paths('database', 'seeders'),
            ])->map(static fn ($directory) => join_paths($workbenchWorkingPath, $directory))
        );

        $this->callSilently('make:provider', [
            'name' => 'WorkbenchServiceProvider',
            '--preset' => 'workbench',
            '--force' => (bool) $this->option('force'),
        ]);

        $this->prepareWorkbenchDatabaseSchema($filesystem, $workbenchWorkingPath);

        foreach (['console', 'web'] as $route) {
            (new GeneratesFile(
                filesystem: $filesystem,
                components: $this->components,
                force: (bool) $this->option('force'),
            ))->handle(
                (string) realpath(join_paths(__DIR__, 'stubs', 'routes', "{$route}.php")),
                join_paths($workbenchWorkingPath, 'routes', "{$route}.php")
            );
        }
    }

    /**
     * Prepare workbench namespace to `composer.json`.
     */
    protected function prepareWorkbenchNamespaces(Filesystem $filesystem, string $workingPath): void
    {
        $composer = (new Composer($filesystem))->setWorkingPath($workingPath);

        $composer->modify(fn (array $content) => $this->appendScriptsToComposer(
            $this->appendAutoloadDevToComposer($content, $filesystem), $filesystem
        ));
    }

    /**
     * Prepare workbench database schema including user model, factory and seeder.
     */
    protected function prepareWorkbenchDatabaseSchema(Filesystem $filesystem, string $workingPath): void
    {
        $this->callSilently('make:user-model', [
            '--preset' => 'workbench',
            '--force' => (bool) $this->option('force'),
        ]);

        $this->callSilently('make:user-factory', [
            '--preset' => 'workbench',
            '--force' => (bool) $this->option('force'),
        ]);

        (new GeneratesFile(
            filesystem: $filesystem,
            components: $this->components,
            force: (bool) $this->option('force'),
        ))->handle(
            (string) realpath(join_paths(__DIR__, 'stubs', 'database', 'seeders', 'DatabaseSeeder.php')),
            join_paths($workingPath, 'database', 'seeders', 'DatabaseSeeder.php')
        );

        if ($filesystem->exists(join_paths($workingPath, 'database', 'factories', 'UserFactory.php'))) {
            $filesystem->replaceInFile([
                'use Orchestra\Testbench\Factories\UserFactory;',
            ], [
                'use Workbench\Database\Factories\UserFactory;',
            ], join_paths($workingPath, 'database', 'seeders', 'DatabaseSeeder.php'));
        }
    }

    /**
     * Append `scripts` to `composer.json`.
     */
    protected function appendScriptsToComposer(array $content, Filesystem $filesystem): array
    {
        $hasScriptsSection = \array_key_exists('scripts', $content);
        $hasTestbenchDusk = InstalledVersions::isInstalled('orchestra/testbench-dusk');

        if (! $hasScriptsSection) {
            $content['scripts'] = [];
        }

        $postAutoloadDumpScripts = array_filter([
            '@clear',
            '@prepare',
            $hasTestbenchDusk ? '@dusk:install-chromedriver' : null,
        ]);

        if (! \array_key_exists('post-autoload-dump', $content['scripts'])) {
            $content['scripts']['post-autoload-dump'] = $postAutoloadDumpScripts;
        } else {
            $content['scripts']['post-autoload-dump'] = array_values(array_unique([
                ...$postAutoloadDumpScripts,
                ...Arr::wrap($content['scripts']['post-autoload-dump']),
            ]));
        }

        $content['scripts']['clear'] = '@php vendor/bin/testbench package:purge-skeleton --ansi';
        $content['scripts']['prepare'] = '@php vendor/bin/testbench package:discover --ansi';

        if ($hasTestbenchDusk) {
            $content['scripts']['dusk:install-chromedriver'] = '@php vendor/bin/dusk-updater detect --auto-update --ansi';
        }

        $content['scripts']['build'] = '@php vendor/bin/testbench workbench:build --ansi';
        $content['scripts']['serve'] = [
            'Composer\\Config::disableProcessTimeout',
            '@build',
            $hasTestbenchDusk && \defined('TESTBENCH_DUSK')
                ? '@php vendor/bin/testbench-dusk serve --ansi'
                : '@php vendor/bin/testbench serve --ansi',
        ];

        if (! \array_key_exists('lint', $content['scripts'])) {
            $lintScripts = [];

            if (InstalledVersions::isInstalled('laravel/pint')) {
                $lintScripts[] = '@php vendor/bin/pint --ansi';
            } elseif ($filesystem->exists(Workbench::packagePath('pint.json'))) {
                $lintScripts[] = 'pint';
            }

            if (InstalledVersions::isInstalled('phpstan/phpstan')) {
                $lintScripts[] = '@php vendor/bin/phpstan analyse --verbose --ansi';
            }

            if (\count($lintScripts) > 0) {
                $content['scripts']['lint'] = $lintScripts;
            }
        }

        if (
            $filesystem->exists(Workbench::packagePath('phpunit.xml'))
            || $filesystem->exists(Workbench::packagePath('phpunit.xml.dist'))
        ) {
            if (! \array_key_exists('test', $content['scripts'])) {
                $content['scripts']['test'][] = InstalledVersions::isInstalled('pestphp/pest')
                    ? '@php vendor/bin/pest'
                    : '@php vendor/bin/phpunit';
            }
        }

        return $content;
    }

    /**
     * Append `autoload-dev` to `composer.json`.
     */
    protected function appendAutoloadDevToComposer(array $content, Filesystem $filesystem): array
    {
        /** @var array{autoload-dev?: array{psr-4?: array<string, string>}} $content */
        if (! \array_key_exists('autoload-dev', $content)) {
            $content['autoload-dev'] = [];
        }

        /** @var array{autoload-dev: array{psr-4?: array<string, string>}} $content */
        if (! \array_key_exists('psr-4', $content['autoload-dev'])) {
            $content['autoload-dev']['psr-4'] = [];
        }

        $namespaces = [
            'Workbench\\App\\' => 'workbench/app/',
            'Workbench\\Database\\Factories\\' => 'workbench/database/factories/',
            'Workbench\\Database\\Seeders\\' => 'workbench/database/seeders/',
        ];

        foreach ($namespaces as $namespace => $path) {
            if (! \array_key_exists($namespace, $content['autoload-dev']['psr-4'])) {
                $content['autoload-dev']['psr-4'][$namespace] = $path;

                $this->components->task(\sprintf(
                    'Added [%s] for [%s] to Composer', $namespace, $path
                ));
            } else {
                $this->components->twoColumnDetail(
                    \sprintf('Composer already contain [%s] namespace', $namespace),
                    '<fg=yellow;options=bold>SKIPPED</>'
                );
            }
        }

        return $content;
    }

    /**
     * Prompt the user for any missing arguments.
     *
     * @return void
     */
    protected function promptForMissingArguments(InputInterface $input, OutputInterface $output)
    {
        $install = null;

        if ($input->getOption('skip-install') === true) {
            $install = false;
        } elseif (\is_null($input->getOption('install'))) {
            $install = confirm('Run Workbench installation?', true);
        }

        if (! \is_null($install)) {
            $input->setOption('install', $install);
        }
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Overwrite any existing files'],
            ['install', null, InputOption::VALUE_NEGATABLE, 'Run Workbench installation'],

            /** @deprecated */
            ['skip-install', null, InputOption::VALUE_NONE, 'Skipped Workbench installation (deprecated)'],
        ];
    }
}
