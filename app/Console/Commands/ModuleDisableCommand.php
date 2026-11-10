<?php

namespace App\Console\Commands;

use App\Actions\Modules\DisableModule;
use App\Models\Team;
use App\Modules\ModuleException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('module:disable {key : The module key} {--team= : The slug of the organization}')]
#[Description('Disable a module for an organization, keeping its data')]
class ModuleDisableCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(DisableModule $disable): int
    {
        $team = Team::where('slug', $this->option('team'))->first();

        if (! $team) {
            $this->components->error('Pass the organization with --team=<slug>.');

            return self::FAILURE;
        }

        try {
            $disable->handle($team, $this->argument('key'));
        } catch (ModuleException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Disabled [{$this->argument('key')}] for {$team->name}. Its data was kept.");

        return self::SUCCESS;
    }
}
