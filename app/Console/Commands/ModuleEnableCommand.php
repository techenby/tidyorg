<?php

namespace App\Console\Commands;

use App\Actions\Modules\EnableModule;
use App\Models\Team;
use App\Modules\ModuleException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('module:enable {key : The module key} {--team= : The slug of the organization}')]
#[Description('Enable a module for an organization')]
class ModuleEnableCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(EnableModule $enable): int
    {
        $team = Team::where('slug', $this->option('team'))->first();

        if (! $team) {
            $this->components->error('Pass the organization with --team=<slug>.');

            return self::FAILURE;
        }

        try {
            $enable->handle($team, $this->argument('key'));
        } catch (ModuleException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Enabled [{$this->argument('key')}] for {$team->name}.");

        return self::SUCCESS;
    }
}
