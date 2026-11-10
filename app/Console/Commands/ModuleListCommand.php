<?php

namespace App\Console\Commands;

use App\Models\TeamModule;
use App\Modules\Module;
use App\Modules\ModuleRegistry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('module:list')]
#[Description('List installed modules and where they are in use')]
class ModuleListCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ModuleRegistry $modules): int
    {
        if ($modules->installed()->isEmpty()) {
            $this->components->info('No modules are installed.');

            return self::SUCCESS;
        }

        $enabledCounts = TeamModule::query()
            ->selectRaw('module, count(*) as total')
            ->groupBy('module')
            ->pluck('total', 'module');

        $this->table(
            ['Key', 'Name', 'Active', 'Organizations', 'Requires'],
            $modules->installed()->map(fn (Module $module) => [
                $module->key(),
                $module->name(),
                $modules->isActive($module->key()) ? 'yes' : 'no',
                $enabledCounts[$module->key()] ?? 0,
                implode(', ', $module->dependencies()),
            ])->all(),
        );

        return self::SUCCESS;
    }
}
