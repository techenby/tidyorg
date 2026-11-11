<?php

use App\Modules\Person;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use TidyOrg\Inventory\Actions\DuplicateItem;
use TidyOrg\Inventory\Actions\MoveItemToTeam;
use TidyOrg\Inventory\Actions\StoreItemPhoto;
use TidyOrg\Inventory\Enums\ItemType;
use TidyOrg\Inventory\Livewire\InventoryPage;
use TidyOrg\Inventory\Models\Item;

new #[Title('Inventory')] class extends InventoryPage {
    #[Url]
    public string $search = '';

    public string $sortBy = 'name';

    public string $sortDirection = 'asc';

    /** @var array{types: list<string>, showTrashed: bool} */
    #[Url]
    public array $filters = [
        'types' => [],
        'showTrashed' => false,
    ];

    #[Url]
    public ?int $parentId = null;

    /** @var array<int, int|string> */
    public array $selected = [];

    public ?int $bulkParentId = null;

    public ?int $moveItemId = null;

    public ?int $moveToTeamId = null;

    public ?int $duplicateItemId = null;

    public int $duplicateCount = 1;

    public function mount(): void
    {
        Gate::authorize('viewAny', [Item::class, $this->team]);
    }

    #[Computed]
    public function showingTrashed(): bool
    {
        return (bool) ($this->filters['showTrashed'] ?? false);
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function typeFilter(): array
    {
        return collect((array) ($this->filters['types'] ?? []))
            ->filter(fn (mixed $type): bool => is_string($type) && ItemType::tryFrom($type) !== null)
            ->values()
            ->all();
    }

    #[Computed]
    public function areFiltersActive(): bool
    {
        return $this->showingTrashed || $this->typeFilter !== [];
    }

    #[Computed]
    public function parentItem(): ?Item
    {
        return $this->parentId ? Item::forTeam($this->team)->find($this->parentId) : null;
    }

    /**
     * @return BaseCollection<int, Item>
     */
    #[Computed]
    public function breadcrumbs(): BaseCollection
    {
        $breadcrumbs = collect();
        $current = $this->parentItem;

        while ($current) {
            $breadcrumbs->prepend($current);
            $current = $current->parent;
        }

        return $breadcrumbs;
    }

    /**
     * @return LengthAwarePaginator<int, Item>
     */
    #[Computed]
    public function items(): LengthAwarePaginator
    {
        $searching = filled($this->search);

        return Item::forTeam($this->team)
            ->withCount('children')
            ->when($searching, fn ($query) => $query
                ->with('parent')
                ->whereRaw('lower(name) like ?', ['%'.Str::lower($this->search).'%']))
            ->when(! $searching && ! $this->showingTrashed, fn ($query) => $query->where('parent_id', $this->parentItem?->id))
            ->when($this->showingTrashed, fn ($query) => $query->onlyTrashed())
            ->when($this->typeFilter !== [], fn ($query) => $query->whereIn('type', $this->typeFilter))
            ->orderBy(in_array($this->sortBy, ['name', 'updated_at'], true) ? $this->sortBy : 'name', $this->sortDirection === 'desc' ? 'desc' : 'asc')
            ->orderBy('id')
            ->paginate(10);
    }

    /**
     * Everything the selected items could be moved into: not themselves, and nothing inside them.
     * Empty until something is selected, so the page does not load every item on each render.
     *
     * @return Collection<int, Item>
     */
    #[Computed]
    public function bulkParentOptions(): Collection
    {
        $selected = $this->selectedItems();

        if ($selected->isEmpty()) {
            return new Collection;
        }

        $excluded = $selected->modelKeys();

        foreach ($selected as $item) {
            $excluded = [...$excluded, ...$item->descendantIds()];
        }

        return Item::forTeam($this->team)
            ->whereNotIn('id', $excluded)
            ->orderBy('name')
            ->get();
    }

    /**
     * Who has each checked-out item on this page, keyed by person id. Empty without a people directory.
     *
     * @return BaseCollection<int, Person>
     */
    #[Computed]
    public function borrowers(): BaseCollection
    {
        return $this->borrowersOf($this->items->items());
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilters(): void
    {
        $this->reset('selected');
        $this->resetPage();
    }

    public function updatedParentId(): void
    {
        $this->reset('selected');
        $this->resetPage();
    }

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    public function open(int $id): void
    {
        $item = Item::forTeam($this->team)->withCount('children')->findOrFail($id);

        if ($item->children_count === 0) {
            $this->redirectRoute('inventory.show', ['current_team' => $this->team, 'item' => $item], navigate: true);

            return;
        }

        $this->browse($item->id);
    }

    public function browse(?int $id = null): void
    {
        $this->parentId = $id;
        $this->reset('selected', 'search');
        $this->resetPage();
    }

    public function navigateUp(): void
    {
        $this->browse($this->parentItem?->parent_id);
    }

    public function create(): void
    {
        Gate::authorize('create', [Item::class, $this->team]);

        $this->form->start($this->parentItem?->id, ItemType::Item->value);
        $this->resetValidation();
        $this->modal('item-form')->show();
    }

    public function edit(int $id): void
    {
        $item = Item::forTeam($this->team)->findOrFail($id);

        Gate::authorize('update', $item);

        $this->form->load($item);
        $this->resetValidation();
        $this->modal('item-form')->show();
    }

    public function save(): void
    {
        $this->saveForm();

        $this->modal('item-form')->close();
    }

    public function showQrCode(int $id): void
    {
        $this->openQrCode(Item::forTeam($this->team)->findOrFail($id));
    }

    public function openDuplicateModal(int $id): void
    {
        $this->duplicateItemId = $id;
        $this->duplicateCount = 1;
        $this->modal('duplicate-item')->show();
    }

    public function duplicate(DuplicateItem $duplicateItem): void
    {
        $this->validate([
            'duplicateItemId' => ['required', 'integer'],
            'duplicateCount' => ['required', 'integer', 'min:1', 'max:25'],
        ]);

        $item = Item::forTeam($this->team)->findOrFail($this->duplicateItemId);

        Gate::authorize('create', [Item::class, $this->team]);

        $copies = $duplicateItem->handle($item, $this->duplicateCount);

        $this->modal('duplicate-item')->close();
        $this->reset('duplicateItemId', 'duplicateCount');

        Flux::toast(variant: 'success', text: trans_choice(':count item duplicated.|:count items duplicated.', $copies->count()));
    }

    public function openMoveModal(int $id): void
    {
        $this->moveItemId = $id;
        $this->moveToTeamId = null;
        $this->modal('move-item')->show();
    }

    public function moveToTeam(MoveItemToTeam $moveItemToTeam): void
    {
        $this->validate([
            'moveItemId' => ['required', 'integer'],
            'moveToTeamId' => ['required', 'integer', Rule::in($this->otherTeams->modelKeys())],
        ]);

        $item = Item::forTeam($this->team)->findOrFail($this->moveItemId);
        $destination = $this->otherTeams->find($this->moveToTeamId);

        Gate::authorize('move', [$item, $destination]);

        $moveItemToTeam->handle($item, $destination);

        $this->modal('move-item')->close();
        $this->reset('moveItemId', 'moveToTeamId');

        Flux::toast(variant: 'success', text: __('Item moved to :organization.', ['organization' => $destination->name]));
    }

    public function openBulkUpdateParentModal(): void
    {
        $this->bulkParentId = $this->parentItem?->id;
        $this->modal('bulk-update-parent')->show();
    }

    public function updateParent(): void
    {
        $this->validate([
            'selected' => ['required', 'array', 'min:1'],
            'selected.*' => ['integer'],
            'bulkParentId' => ['nullable', 'integer', Rule::in($this->bulkParentOptions->modelKeys())],
        ]);

        $items = $this->selectedItems();

        foreach ($items as $item) {
            Gate::authorize('update', $item);
        }

        $items->each->update(['parent_id' => $this->bulkParentId ?: null]);

        $this->modal('bulk-update-parent')->close();
        $this->reset('selected', 'bulkParentId');
        unset($this->bulkParentOptions);

        Flux::toast(variant: 'success', text: trans_choice(':count item moved.|:count items moved.', $items->count()));
    }

    public function delete(int $id): void
    {
        $item = Item::forTeam($this->team)->findOrFail($id);

        Gate::authorize('delete', $item);

        $item->purge();
    }

    public function bulkDelete(): void
    {
        $items = $this->selectedItems();

        foreach ($items as $item) {
            Gate::authorize('delete', $item);
        }

        $items->each->purge();

        $this->reset('selected');

        Flux::toast(variant: 'success', text: trans_choice(':count item deleted.|:count items deleted.', $items->count()));
    }

    public function restore(int $id): void
    {
        $item = Item::forTeam($this->team)->onlyTrashed()->findOrFail($id);

        Gate::authorize('restore', $item);

        $item->restore();
    }

    public function bulkRestore(): void
    {
        $items = $this->selectedItems(trashed: true);

        foreach ($items as $item) {
            Gate::authorize('restore', $item);
        }

        $items->each->restore();

        $this->reset('selected');

        Flux::toast(variant: 'success', text: trans_choice(':count item restored.|:count items restored.', $items->count()));
    }

    public function forceDelete(int $id): void
    {
        $item = Item::forTeam($this->team)->onlyTrashed()->findOrFail($id);

        Gate::authorize('forceDelete', $item);

        app(StoreItemPhoto::class)->remove($item);

        $item->forceDelete();
    }

    /**
     * The selected items, limited to this organization whatever ids the browser sent.
     *
     * @return Collection<int, Item>
     */
    private function selectedItems(bool $trashed = false): Collection
    {
        return Item::forTeam($this->team)
            ->when($trashed, fn ($query) => $query->onlyTrashed())
            ->whereIn('id', array_filter($this->selected, 'is_numeric'))
            ->get();
    }
}; ?>

<div class="space-y-6" data-test="inventory-index">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item wire:click="browse" class="cursor-pointer">
            {{ __('Inventory') }}
        </flux:breadcrumbs.item>

        @foreach ($this->breadcrumbs as $breadcrumb)
            <flux:breadcrumbs.item wire:key="breadcrumb-{{ $breadcrumb->id }}" wire:click="browse({{ $breadcrumb->id }})" class="cursor-pointer">
                {{ $breadcrumb->truncated_name }}
            </flux:breadcrumbs.item>
        @endforeach
    </flux:breadcrumbs>

    <div class="flex items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            @if ($this->parentItem)
                <flux:button wire:click="navigateUp" icon="arrow-left" variant="ghost" :aria-label="__('Back')" />
                <flux:heading size="xl">{{ $this->parentItem->truncated_name }}</flux:heading>
            @else
                <flux:heading size="xl">{{ __('All items') }}</flux:heading>
            @endif
        </div>

        @if ($this->canManage)
            <flux:button variant="primary" icon="plus" wire:click="create" data-test="inventory-add-item">{{ __('Add item') }}</flux:button>
        @endif
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <flux:input wire:model.live.debounce.300ms="search" :placeholder="__('Search inventory...')" icon="magnifying-glass" class="max-w-sm" data-test="inventory-search" />

        <flux:dropdown>
            <flux:button :variant="$this->areFiltersActive ? 'primary' : 'ghost'" icon="funnel" icon:variant="outline" :aria-label="__('Filter')" data-test="inventory-filter" />

            <flux:menu>
                <flux:menu.checkbox.group wire:model.live="filters.types">
                    @foreach (ItemType::cases() as $type)
                        <flux:menu.checkbox :value="$type->value">{{ __($type->label()) }}</flux:menu.checkbox>
                    @endforeach
                </flux:menu.checkbox.group>
                <flux:menu.separator />
                <flux:menu.checkbox wire:model.live="filters.showTrashed">{{ __('Show deleted') }}</flux:menu.checkbox>
            </flux:menu>
        </flux:dropdown>

        @if ($selected !== [] && $this->canManage)
            <flux:dropdown>
                <flux:button icon:trailing="chevron-down" data-test="inventory-bulk-actions">{{ __('Bulk actions') }}</flux:button>

                <flux:menu>
                    @if ($this->showingTrashed)
                        <flux:menu.item wire:click="bulkRestore" icon="arrow-uturn-left">{{ __('Restore') }}</flux:menu.item>
                    @else
                        <flux:menu.item wire:click="openBulkUpdateParentModal" icon="arrows-right-left">{{ __('Change parent') }}</flux:menu.item>

                        @if ($this->canDelete)
                            <flux:menu.separator />
                            <flux:menu.item wire:click="bulkDelete" wire:confirm="{{ __('Are you sure you want to delete the selected items?') }}" variant="danger" icon="trash">{{ __('Delete') }}</flux:menu.item>
                        @endif
                    @endif
                </flux:menu>
            </flux:dropdown>
        @endif
    </div>

    <flux:checkbox.group wire:model.live="selected">
        <flux:table :paginate="$this->items">
            <flux:table.columns>
                @if ($this->canManage)
                    <flux:table.column class="w-8"><flux:checkbox.all /></flux:table.column>
                @endif
                <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">{{ __('Name') }}</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'updated_at'" :direction="$sortDirection" wire:click="sort('updated_at')">{{ __('Modified') }}</flux:table.column>
                <flux:table.column>{{ __('Contents') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->items as $item)
                    <flux:table.row :key="$item->id" data-test="inventory-row">
                        @if ($this->canManage)
                            <flux:table.cell>
                                <flux:checkbox :value="$item->id" />
                            </flux:table.cell>
                        @endif
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                @if ($this->showingTrashed)
                                    <span class="flex items-center gap-3">
                                        <flux:avatar size="xs" :src="$item->thumb_url" :icon="$item->type->icon()" :color="$item->type->color()" icon:variant="outline" />
                                        <span>{{ $item->truncated_name }}</span>
                                    </span>
                                @else
                                    <flux:link wire:click="open({{ $item->id }})" as="button" class="!flex !items-center gap-3">
                                        <flux:avatar size="xs" :src="$item->thumb_url" :icon="$item->type->icon()" :color="$item->type->color()" icon:variant="outline" />
                                        <span>{{ $item->truncated_name }}</span>
                                    </flux:link>
                                @endif

                                @if ($this->directory && $item->checked_out_to)
                                    <flux:badge size="sm" color="sky" icon="arrow-right-start-on-rectangle" data-test="inventory-checked-out">{{ $this->borrowers->get($item->checked_out_to)?->name ?? __('Checked out') }}</flux:badge>
                                @endif

                                @if (filled($search) && $item->parent)
                                    <flux:text size="sm">{{ __('in :parent', ['parent' => $item->parent->truncated_name]) }}</flux:text>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $item->updated_at->diffForHumans() }}</flux:table.cell>
                        <flux:table.cell>{{ $item->children_count }}</flux:table.cell>
                        <flux:table.cell align="end">
                            @if ($this->showingTrashed)
                                <div class="flex items-center justify-end gap-1">
                                    @if ($this->canManage)
                                        <flux:button wire:click="restore({{ $item->id }})" variant="ghost" size="sm" icon="arrow-uturn-left">{{ __('Restore') }}</flux:button>
                                    @endif
                                    @if ($this->canDelete)
                                        <flux:button wire:click="forceDelete({{ $item->id }})" wire:confirm="{{ __('Are you sure you want to permanently delete this item? This cannot be undone.') }}" variant="danger" size="sm" icon="trash">{{ __('Delete forever') }}</flux:button>
                                    @endif
                                </div>
                            @else
                                <flux:dropdown align="end">
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" :aria-label="__('Actions')" data-test="inventory-row-actions" />

                                    <flux:menu>
                                        <flux:menu.item :href="route('inventory.show', ['current_team' => $this->team, 'item' => $item])" icon="eye" wire:navigate>{{ __('View') }}</flux:menu.item>
                                        <flux:menu.item wire:click="showQrCode({{ $item->id }})" icon="qr-code">{{ __('QR code') }}</flux:menu.item>
                                        @if ($this->canManage)
                                            <flux:menu.item wire:click="edit({{ $item->id }})" icon="pencil">{{ __('Edit') }}</flux:menu.item>
                                            <flux:menu.item wire:click="openDuplicateModal({{ $item->id }})" icon="square-2-stack">{{ __('Duplicate') }}</flux:menu.item>
                                            @if ($this->otherTeams->isNotEmpty())
                                                <flux:menu.item wire:click="openMoveModal({{ $item->id }})" icon="arrow-up-tray">{{ __('Move to organization') }}</flux:menu.item>
                                            @endif
                                        @endif
                                        @if ($this->canDelete)
                                            <flux:menu.item wire:click="delete({{ $item->id }})" variant="danger" icon="trash" wire:confirm="{{ __('Are you sure you want to delete this item?') }}">{{ __('Delete') }}</flux:menu.item>
                                        @endif
                                    </flux:menu>
                                </flux:dropdown>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row key="empty">
                        <flux:table.cell colspan="5" class="text-center">
                            <div class="py-8">
                                <flux:text size="lg">{{ filled($search) || $this->areFiltersActive ? __('No items found') : __('Nothing in here yet.') }}</flux:text>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:checkbox.group>

    @include('inventory::modals.item-form')
    @include('inventory::modals.qr-code')
    @include('inventory::modals.move-item')
    @include('inventory::modals.duplicate-item')
    @include('inventory::modals.bulk-update-parent')
</div>
