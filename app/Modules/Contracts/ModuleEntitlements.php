<?php

namespace App\Modules\Contracts;

use App\Models\Team;
use App\Modules\Module;

/**
 * Decides which modules an organization is allowed to enable.
 *
 * The default implementation allows everything. A hosting package may bind
 * its own implementation, for example to reflect a subscription plan.
 */
interface ModuleEntitlements
{
    public function allows(Team $team, Module $module): bool;
}
