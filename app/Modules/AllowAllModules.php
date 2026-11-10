<?php

namespace App\Modules;

use App\Models\Team;
use App\Modules\Contracts\ModuleEntitlements;

class AllowAllModules implements ModuleEntitlements
{
    public function allows(Team $team, Module $module): bool
    {
        return true;
    }
}
