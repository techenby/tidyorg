<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Models\TeamModule;
use App\Modules\Module;
use App\Modules\ModuleRegistry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;

#[Signature('module:purge
    {key : The module key}
    {--team= : Only delete this organization\'s data, by slug}
    {--force : Skip confirmation, and allow a deployment-wide purge when several organizations exist}')]
#[Description('Permanently delete a module\'s data for one organization, or for the whole deployment')]
class ModulePurgeCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ModuleRegistry $modules, Migrator $migrator): int
    {
        $module = $modules->find($this->argument('key'));

        if (! $module) {
            $this->components->error("Module [{$this->argument('key')}] is not installed.");

            return self::FAILURE;
        }

        return $this->option('team') !== null
            ? $this->purgeTeam($module)
            : $this->purgeDeployment($module, $migrator);
    }

    /**
     * Delete one organization's data for the module. Tables are left in place.
     */
    protected function purgeTeam(Module $module): int
    {
        $team = Team::where('slug', $this->option('team'))->first();

        if (! $team) {
            $this->components->error("Organization [{$this->option('team')}] was not found.");

            return self::FAILURE;
        }

        if (! $this->confirmed("Permanently delete all {$module->name()} data for {$team->name}?")) {
            return self::FAILURE;
        }

        $this->purge($module, $team);

        $this->components->info("Purged [{$module->key()}] for {$team->name}.");

        return self::SUCCESS;
    }

    /**
     * Delete every organization's data for the module and drop its tables.
     */
    protected function purgeDeployment(Module $module, Migrator $migrator): int
    {
        if (Team::count() > 1 && ! $this->option('force')) {
            $this->components->error('This deployment has several organizations. Purge one with --team=<slug>, or pass --force to purge them all and drop the module\'s tables.');

            return self::FAILURE;
        }

        if (! $this->confirmed("Permanently delete all {$module->name()} data and drop its tables?")) {
            return self::FAILURE;
        }

        Team::query()->each(fn (Team $team) => $this->purge($module, $team));

        if (is_dir($module->migrationPath())) {
            $migrator->reset([$module->migrationPath()]);
        }

        $this->components->info("Purged [{$module->key()}] and dropped its tables.");

        return self::SUCCESS;
    }

    protected function purge(Module $module, Team $team): void
    {
        DB::transaction(function () use ($module, $team) {
            $module->purging($team);

            TeamModule::where('team_id', $team->id)->where('module', $module->key())->delete();
        });
    }

    protected function confirmed(string $question): bool
    {
        return $this->option('force') || $this->confirm($question);
    }
}
