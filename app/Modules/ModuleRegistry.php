<?php

namespace App\Modules;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Knows which modules are installed, active for this deployment, and enabled for each organization.
 */
class ModuleRegistry
{
    /**
     * @var array<string, Module>
     */
    protected array $modules = [];

    /**
     * Add an installed module to the registry.
     */
    public function add(Module $module): void
    {
        $key = $module->key();

        if (! preg_match('/^[a-z][a-z0-9-]*$/', $key)) {
            throw ModuleException::invalidKey($key);
        }

        if (isset($this->modules[$key])) {
            throw ModuleException::duplicate($key);
        }

        foreach (array_keys($module->permissions()) as $permission) {
            if (! str_starts_with($permission, $key.':')) {
                throw ModuleException::invalidPermission($key, $permission);
            }
        }

        $this->modules[$key] = $module;
    }

    /**
     * Every module whose code is installed.
     *
     * @return Collection<string, Module>
     */
    public function installed(): Collection
    {
        return collect($this->modules);
    }

    /**
     * The installed modules that are switched on for this deployment.
     *
     * @return Collection<string, Module>
     */
    public function active(): Collection
    {
        $configured = config('tessera.modules', '*');

        if ($configured === '*') {
            return $this->installed();
        }

        $keys = is_array($configured)
            ? $configured
            : array_map(trim(...), explode(',', (string) $configured));

        return $this->installed()->only($keys);
    }

    public function find(string $key): ?Module
    {
        return $this->modules[$key] ?? null;
    }

    public function isInstalled(string $key): bool
    {
        return isset($this->modules[$key]);
    }

    public function isActive(string $key): bool
    {
        return $this->active()->has($key);
    }

    /**
     * Determine if a module is enabled for an organization, defaulting to the user's current one.
     */
    public function enabled(string $key, ?Team $team = null): bool
    {
        if (! $this->isActive($key)) {
            return false;
        }

        if (config('tessera.single_organization')) {
            return true;
        }

        $team ??= Auth::user()?->currentTeam;

        return $team !== null && $team->modules->contains('module', $key);
    }

    /**
     * The modules enabled for an organization.
     *
     * @return Collection<string, Module>
     */
    public function enabledFor(Team $team): Collection
    {
        return $this->active()->filter(fn (Module $module) => $this->enabled($module->key(), $team));
    }

    /**
     * The navigation entries a user may see in an organization.
     *
     * @return Collection<int, NavigationItem>
     */
    public function navigationFor(Team $team, User $user): Collection
    {
        return $this->enabledFor($team)
            ->flatMap(fn (Module $module) => $module->navigation())
            ->filter(fn (NavigationItem $item) => $item->permission === null || $this->allows($user, $team, $item->permission))
            ->values();
    }

    /**
     * The dashboard widgets a user may see in an organization.
     *
     * @return Collection<int, Widget>
     */
    public function widgetsFor(Team $team, User $user): Collection
    {
        return $this->enabledFor($team)
            ->flatMap(fn (Module $module) => $module->widgets())
            ->filter(fn (Widget $widget) => $widget->permission === null || $this->allows($user, $team, $widget->permission))
            ->values();
    }

    /**
     * Determine if a user holds a module permission in an organization.
     *
     * A permission is only ever held while its module is enabled for that organization.
     */
    public function allows(User $user, Team $team, string $permission): bool
    {
        $module = $this->find(str($permission)->before(':')->toString());

        $minimumRole = $module?->permissions()[$permission] ?? null;

        if (! $module || ! $minimumRole instanceof TeamRole || ! $this->enabled($module->key(), $team)) {
            return false;
        }

        return $user->teamRole($team)?->isAtLeast($minimumRole) ?? false;
    }

    /**
     * The active modules that depend on the given module.
     *
     * @return Collection<string, Module>
     */
    public function dependents(string $key): Collection
    {
        return $this->active()->filter(fn (Module $module) => in_array($key, $module->dependencies(), true));
    }

    /**
     * Ensure every active module's dependencies are active and free of cycles.
     *
     * @throws ModuleException
     */
    public function validate(): void
    {
        $active = $this->active();

        foreach ($active as $module) {
            foreach ($module->dependencies() as $dependency) {
                if (! $active->has($dependency)) {
                    throw ModuleException::missingDependency($module->key(), $dependency);
                }
            }
        }

        foreach ($active->keys() as $key) {
            $this->detectCycle($key, []);
        }
    }

    /**
     * @param  list<string>  $path
     */
    protected function detectCycle(string $key, array $path): void
    {
        if (in_array($key, $path, true)) {
            throw ModuleException::circularDependency($key);
        }

        foreach ($this->find($key)?->dependencies() ?? [] as $dependency) {
            $this->detectCycle($dependency, [...$path, $key]);
        }
    }
}
