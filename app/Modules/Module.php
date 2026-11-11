<?php

namespace App\Modules;

use App\Enums\TeamRole;
use App\Http\Middleware\EnsureTeamMembership;
use App\Models\Team;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * Base service provider for a Tessera module.
 *
 * A module describes itself by overriding the methods below. Everything it
 * contributes is wired up only when the module is active for this deployment,
 * so an inactive module has no routes, views, or migrations.
 */
abstract class Module extends ServiceProvider
{
    /**
     * The unique, URL-safe key for the module, such as "inventory".
     */
    abstract public function key(): string;

    /**
     * The human-readable name of the module.
     */
    abstract public function name(): string;

    /**
     * The absolute path to the module's root directory.
     */
    abstract public function path(): string;

    /**
     * A one-sentence description shown where organizations choose modules.
     */
    public function description(): string
    {
        return '';
    }

    /**
     * The icon shown alongside the module's name.
     */
    public function icon(): string
    {
        return 'puzzle-piece';
    }

    /**
     * The keys of the modules this module cannot work without.
     *
     * @return list<string>
     */
    public function dependencies(): array
    {
        return [];
    }

    /**
     * The permissions the module defines, each mapped to the lowest role that holds it.
     *
     * Permission names must be prefixed with the module key, such as "inventory:view".
     *
     * @return array<string, TeamRole>
     */
    public function permissions(): array
    {
        return [];
    }

    /**
     * The entries the module adds to the main navigation.
     *
     * @return list<NavigationItem>
     */
    public function navigation(): array
    {
        return [];
    }

    /**
     * The widgets the module adds to the organization dashboard.
     *
     * @return list<Widget>
     */
    public function widgets(): array
    {
        return [];
    }

    /**
     * What the module offers to other modules: core contracts mapped to its implementations.
     *
     * This is how modules work together without depending on each other. One
     * module provides a contract from App\Modules\Contracts, and others ask
     * the registry for it with capability(), working without it when absent.
     *
     * @return array<class-string, class-string>
     */
    public function capabilities(): array
    {
        return [];
    }

    /**
     * Run when the module is being enabled for an organization.
     */
    public function enabling(Team $team): void
    {
        //
    }

    /**
     * Run after the module has been disabled for an organization. Must not delete data.
     */
    public function disabled(Team $team): void
    {
        //
    }

    /**
     * Permanently delete everything the module stores for an organization.
     */
    public function purging(Team $team): void
    {
        //
    }

    /**
     * Bootstrap anything beyond routes, views, migrations, and translations.
     */
    protected function bootModule(): void
    {
        //
    }

    /**
     * Register the module with the registry.
     */
    public function register(): void
    {
        $this->app->singletonIf(ModuleRegistry::class);

        $this->app->make(ModuleRegistry::class)->add($this);
    }

    /**
     * Wire up the module when it is active for this deployment.
     */
    public function boot(): void
    {
        if (! $this->app->make(ModuleRegistry::class)->isActive($this->key())) {
            return;
        }

        $this->bootRoutes();
        $this->bootViews();
        $this->bootMigrations();
        $this->bootTranslations();
        $this->bootModule();
    }

    /**
     * The directory holding the module's migrations.
     */
    public function migrationPath(): string
    {
        return $this->path().'/database/migrations';
    }

    /**
     * Register the module's routes inside the organization, behind membership and module checks.
     */
    protected function bootRoutes(): void
    {
        $routes = $this->path().'/routes/web.php';

        if (! is_file($routes) || $this->app->routesAreCached()) {
            return;
        }

        Route::middleware(['web', 'auth', 'verified', EnsureTeamMembership::class, 'module:'.$this->key()])
            ->prefix('{current_team}/'.$this->key())
            ->name($this->key().'.')
            ->group($routes);
    }

    /**
     * Register the module's views under its key, for both Blade and Livewire.
     */
    protected function bootViews(): void
    {
        $views = $this->path().'/resources/views';

        if (! is_dir($views)) {
            return;
        }

        $this->loadViewsFrom($views, $this->key());

        Livewire::addNamespace($this->key(), viewPath: $views);
    }

    /**
     * Register the module's migrations.
     */
    protected function bootMigrations(): void
    {
        if (is_dir($this->migrationPath())) {
            $this->loadMigrationsFrom($this->migrationPath());
        }
    }

    /**
     * Register the module's translations under its key.
     */
    protected function bootTranslations(): void
    {
        $lang = $this->path().'/lang';

        if (is_dir($lang)) {
            $this->loadTranslationsFrom($lang, $this->key());
        }
    }
}
