<?php

namespace App\Modules\Facades;

use App\Models\Team;
use App\Models\User;
use App\Modules\Module;
use App\Modules\ModuleRegistry;
use App\Modules\NavigationItem;
use App\Modules\Widget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Collection<string, Module> installed()
 * @method static Collection<string, Module> active()
 * @method static Module|null find(string $key)
 * @method static bool isInstalled(string $key)
 * @method static bool isActive(string $key)
 * @method static bool enabled(string $key, ?Team $team = null)
 * @method static Collection<string, Module> enabledFor(Team $team)
 * @method static Collection<int, NavigationItem> navigationFor(Team $team, User $user)
 * @method static Collection<int, Widget> widgetsFor(Team $team, User $user)
 * @method static bool allows(User $user, Team $team, string $permission)
 *
 * @see ModuleRegistry
 */
class Modules extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ModuleRegistry::class;
    }
}
