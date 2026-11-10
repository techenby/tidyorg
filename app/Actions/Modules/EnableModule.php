<?php

namespace App\Actions\Modules;

use App\Models\Team;
use App\Modules\Contracts\ModuleEntitlements;
use App\Modules\ModuleException;
use App\Modules\ModuleRegistry;
use Illuminate\Support\Facades\DB;

class EnableModule
{
    public function __construct(
        private ModuleRegistry $modules,
        private ModuleEntitlements $entitlements,
    ) {
        //
    }

    /**
     * Enable a module for an organization.
     *
     * @throws ModuleException
     */
    public function handle(Team $team, string $key): void
    {
        $module = $this->modules->active()->get($key) ?? throw ModuleException::notActive($key);

        if (! $this->entitlements->allows($team, $module)) {
            throw ModuleException::notEntitled($module->name());
        }

        foreach ($module->dependencies() as $dependency) {
            if (! $this->modules->enabled($dependency, $team)) {
                throw ModuleException::dependencyNotEnabled(
                    $module->name(),
                    $this->modules->find($dependency)?->name() ?? $dependency,
                );
            }
        }

        DB::transaction(function () use ($team, $module) {
            $module->enabling($team);

            $team->modules()->firstOrCreate(['module' => $module->key()]);
        });

        $team->unsetRelation('modules');
    }
}
