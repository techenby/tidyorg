<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

#[Signature('make:module {name : The name of the module, such as "Events"} {--path= : The directory that holds modules; defaults to modules/}')]
#[Description('Scaffold a new Tessera module')]
class MakeModuleCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(Filesystem $files): int
    {
        $name = Str::of($this->argument('name'))->trim()->headline()->toString();
        $key = Str::slug($name);
        $studly = Str::studly($key);

        if (! preg_match('/^[a-z][a-z0-9-]*$/', $key)) {
            $this->components->error('A module name must start with a letter and contain only letters, numbers, and spaces.');

            return self::FAILURE;
        }

        $directory = rtrim($this->option('path') ?: base_path('modules'), '/').'/'.$key;

        if ($files->exists($directory)) {
            $this->components->error("A module already exists at [{$directory}].");

            return self::FAILURE;
        }

        $replacements = ['{{ key }}' => $key, '{{ name }}' => $name, '{{ studly }}' => $studly];

        foreach ($this->files($studly) as $stub => $target) {
            $files->ensureDirectoryExists(dirname("{$directory}/{$target}"));

            $files->put(
                "{$directory}/{$target}",
                strtr($files->get(__DIR__."/stubs/module/{$stub}"), $replacements),
            );
        }

        $files->ensureDirectoryExists("{$directory}/database/migrations");
        $files->put("{$directory}/database/migrations/.gitkeep", '');

        $this->components->info("Module [{$name}] created at [{$directory}].");

        $this->components->bulletList([
            "Install it: composer require tessera/{$key}:@dev",
            "Enable it for an organization: php artisan module:enable {$key} --team=<slug>",
            "Prefix its tables with [{$key}_] and give every tenant-owned row a team_id.",
            'Read docs/architecture.md before adding to it.',
        ]);

        return self::SUCCESS;
    }

    /**
     * The stubs to write, mapped to their paths inside the module.
     *
     * @return array<string, string>
     */
    private function files(string $studly): array
    {
        return [
            'composer.json.stub' => 'composer.json',
            'Module.php.stub' => "src/{$studly}Module.php",
            'routes.php.stub' => 'routes/web.php',
            'index.blade.php.stub' => 'resources/views/⚡index.blade.php',
            'Test.php.stub' => "tests/{$studly}ModuleTest.php",
        ];
    }
}
