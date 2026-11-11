<?php

namespace Tessera\Inventory;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Modules\Module;
use App\Modules\NavigationItem;
use App\Modules\Widget;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tessera\Inventory\Http\Controllers\OpenItemLinkController;
use Tessera\Inventory\Models\Item;
use Tessera\Inventory\Policies\ItemPolicy;

class InventoryModule extends Module
{
    public function key(): string
    {
        return 'inventory';
    }

    public function name(): string
    {
        return 'Inventory';
    }

    public function description(): string
    {
        return 'Track what your organization owns and where it is kept.';
    }

    public function icon(): string
    {
        return 'archive-box';
    }

    public function path(): string
    {
        return dirname(__DIR__);
    }

    public function permissions(): array
    {
        return [
            'inventory:view' => TeamRole::Member,
            'inventory:manage' => TeamRole::Member,
            'inventory:delete' => TeamRole::Admin,
        ];
    }

    public function navigation(): array
    {
        return [
            new NavigationItem('Inventory', 'inventory.index', 'archive-box', 'inventory:view'),
        ];
    }

    public function widgets(): array
    {
        return [
            new Widget('inventory::widget', 'inventory:view'),
        ];
    }

    /**
     * Delete the organization's items and their stored photos.
     */
    public function purging(Team $team): void
    {
        Storage::deleteDirectory(Item::storageDirectory($team->id));

        Item::withTrashed()->where('team_id', $team->id)->forceDelete();
    }

    protected function bootModule(): void
    {
        Gate::policy(Item::class, ItemPolicy::class);

        if ($this->app->routesAreCached()) {
            return;
        }

        Route::middleware(['web', 'auth', 'verified'])
            ->get('i/{item}', OpenItemLinkController::class)
            ->whereNumber('item')
            ->withTrashed()
            ->name('inventory.link');
    }
}
