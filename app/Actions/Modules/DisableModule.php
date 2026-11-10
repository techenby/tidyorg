<?php

namespace App\Actions\Modules;

use App\Models\Team;
use App\Modules\ModuleException;
use App\Modules\ModuleRegistry;

class DisableModule
{
    public function __construct(private ModuleRegistry $modules)
    {
        //
    }

    /**
     * Disable a module for an organization. The module's data is kept.
     *
     * @throws ModuleException
     */
    public function handle(Team $team, string $key): void
    {
        foreach ($this->modules->dependents($key) as $dependent) {
            if ($team->modules->contains('module', $dependent->key())) {
                throw ModuleException::requiredBy(
                    $this->modules->find($key)?->name() ?? $key,
                    $dependent->name(),
                );
            }
        }

        $team->modules()->where('module', $key)->delete();

        $team->unsetRelation('modules');

        $this->modules->find($key)?->disabled($team);
    }
}
