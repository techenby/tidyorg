<?php

use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use App\Modules\Person;
use TidyOrg\Inventory\Actions\CheckInItem;
use TidyOrg\Inventory\Actions\CheckOutItem;
use TidyOrg\Inventory\Actions\DuplicateItem;
use TidyOrg\Inventory\Actions\MoveItemToTeam;
use TidyOrg\Inventory\Enums\ItemType;
use TidyOrg\Inventory\Livewire\InventoryPage;
use TidyOrg\Inventory\Models\Item;

new #[Title('Inventory')] class extends InventoryPage {
    #[Locked]
    public Item $item;

    public ?int $moveToTeamId = null;

    public int $duplicateCount = 1;

    public ?int $checkOutTo = null;

    /**
     * An item reached through another organization's URL does not exist as far as that organization goes.
     */
    public function mount(Item $item): void
    {
        abort_unless($item->team_id === $this->team->id, 404);

        Gate::authorize('view', $item);

        $this->item = $item;
    }

    /**
     * @return BaseCollection<int, Item>
     */
    #[Computed]
    public function breadcrumbs(): BaseCollection
    {
        $breadcrumbs = collect();
        $current = $this->item->parent;

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
    public function children(): LengthAwarePaginator
    {
        return $this->item->children()
            ->withCount('children')
            ->orderBy('name')
            ->paginate(10);
    }

    /**
     * Everyone the item could be checked out to. Empty without a people directory.
     *
     * @return BaseCollection<int, Person>
     */
    #[Computed]
    public function people(): BaseCollection
    {
        return $this->directory?->people($this->team) ?? collect();
    }

    /**
     * The person who has the item, or null when it is in, or they have left the directory.
     */
    #[Computed]
    public function borrower(): ?Person
    {
        return $this->borrowersOf([$this->item])->first();
    }

    public function checkOut(CheckOutItem $checkOutItem): void
    {
        abort_if($this->directory === null || $this->item->trashed(), 404);

        Gate::authorize('update', $this->item);

        $this->validate([
            'checkOutTo' => ['required', 'integer', Rule::in($this->people->pluck('id')->all())],
        ]);

        $checkOutItem->handle($this->item, $this->people->firstWhere('id', $this->checkOutTo));

        $this->modal('check-out')->close();
        $this->reset('checkOutTo');
        unset($this->borrower);
    }

    public function checkIn(CheckInItem $checkInItem): void
    {
        abort_if($this->directory === null, 404);

        Gate::authorize('update', $this->item);

        $checkInItem->handle($this->item);

        unset($this->borrower);
    }

    public function create(): void
    {
        Gate::authorize('create', [Item::class, $this->team]);

        $this->form->start($this->item->id, ItemType::Item->value);
        $this->resetValidation();
        $this->modal('item-form')->show();
    }

    public function edit(): void
    {
        Gate::authorize('update', $this->item);

        $this->form->load($this->item);
        $this->resetValidation();
        $this->modal('item-form')->show();
    }

    public function save(): void
    {
        $this->saveForm();

        $this->modal('item-form')->close();
        $this->item->refresh();
    }

    public function showQrCode(): void
    {
        $this->openQrCode($this->item);
    }

    public function duplicate(DuplicateItem $duplicateItem): void
    {
        $this->validate([
            'duplicateCount' => ['required', 'integer', 'min:1', 'max:25'],
        ]);

        Gate::authorize('create', [Item::class, $this->team]);

        $copies = $duplicateItem->handle($this->item, $this->duplicateCount);

        $this->modal('duplicate-item')->close();
        $this->reset('duplicateCount');

        Flux::toast(variant: 'success', text: trans_choice(':count item duplicated.|:count items duplicated.', $copies->count()));
    }

    public function moveToTeam(MoveItemToTeam $moveItemToTeam): void
    {
        $this->validate([
            'moveToTeamId' => ['required', 'integer', Rule::in($this->otherTeams->modelKeys())],
        ]);

        $destination = $this->otherTeams->find($this->moveToTeamId);

        Gate::authorize('move', [$this->item, $destination]);

        $moveItemToTeam->handle($this->item, $destination);

        Flux::toast(variant: 'success', text: __('Item moved to :organization.', ['organization' => $destination->name]));

        $this->redirectRoute('inventory.index', ['current_team' => $this->team], navigate: true);
    }

    public function delete(): void
    {
        Gate::authorize('delete', $this->item);

        $parentId = $this->item->parent_id;

        $this->item->purge();

        $this->redirectRoute('inventory.index', ['current_team' => $this->team, 'parentId' => $parentId], navigate: true);
    }

    public function restore(): void
    {
        Gate::authorize('restore', $this->item);

        $this->item->restore();
    }
}; ?>

<div class="space-y-6" data-test="inventory-show">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('inventory.index', ['current_team' => $this->team])" wire:navigate>
            {{ __('Inventory') }}
        </flux:breadcrumbs.item>

        @foreach ($this->breadcrumbs as $breadcrumb)
            <flux:breadcrumbs.item wire:key="breadcrumb-{{ $breadcrumb->id }}" :href="route('inventory.index', ['current_team' => $this->team, 'parentId' => $breadcrumb->id])" wire:navigate>
                {{ $breadcrumb->truncated_name }}
            </flux:breadcrumbs.item>
        @endforeach

        <flux:breadcrumbs.item>{{ $item->truncated_name }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    @if ($item->trashed())
        <flux:callout icon="trash" variant="warning" data-test="inventory-deleted-notice">
            <flux:callout.heading>{{ __('This item has been deleted.') }}</flux:callout.heading>
            @if ($this->canManage)
                <x-slot name="actions">
                    <flux:button wire:click="restore" size="sm">{{ __('Restore') }}</flux:button>
                </x-slot>
            @endif
        </flux:callout>
    @endif

    <div class="flex items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <flux:button :href="route('inventory.index', ['current_team' => $this->team, 'parentId' => $item->parent_id])" icon="arrow-left" variant="ghost" :aria-label="__('Back')" wire:navigate />
            <flux:avatar size="sm" :src="$item->thumb_url" :icon="$item->type->icon()" :color="$item->type->color()" icon:variant="outline" />
            <div>
                <flux:heading size="xl">{{ $item->name }}</flux:heading>
                <flux:text>
                    {{ __($item->type->label()) }}
                    @if ($this->breadcrumbs->isNotEmpty())
                        · {{ __('in') }}
                        <flux:link :href="route('inventory.show', ['current_team' => $this->team, 'item' => $this->breadcrumbs->last()])" wire:navigate>{{ $this->breadcrumbs->last()->truncated_name }}</flux:link>
                    @endif
                </flux:text>
            </div>
        </div>

        @unless ($item->trashed())
            <div class="flex items-center gap-1">
                @if ($this->canManage)
                    <flux:button variant="primary" icon="plus" wire:click="create" data-test="inventory-add-item">{{ __('Add item') }}</flux:button>
                @endif

                <flux:dropdown align="end">
                    <flux:button icon="ellipsis-vertical" variant="ghost" :aria-label="__('Actions')" data-test="inventory-item-actions" />

                    <flux:menu>
                        <flux:menu.item wire:click="showQrCode" icon="qr-code">{{ __('QR code') }}</flux:menu.item>
                        @if ($this->canManage)
                            <flux:menu.item wire:click="edit" icon="pencil">{{ __('Edit') }}</flux:menu.item>
                            <flux:modal.trigger name="duplicate-item">
                                <flux:menu.item icon="square-2-stack">{{ __('Duplicate') }}</flux:menu.item>
                            </flux:modal.trigger>
                            @if ($this->otherTeams->isNotEmpty())
                                <flux:modal.trigger name="move-item">
                                    <flux:menu.item icon="arrow-up-tray">{{ __('Move to organization') }}</flux:menu.item>
                                </flux:modal.trigger>
                            @endif
                        @endif
                        @if ($this->canDelete)
                            <flux:menu.item wire:click="delete" variant="danger" icon="trash" wire:confirm="{{ __('Are you sure you want to delete this item?') }}">{{ __('Delete') }}</flux:menu.item>
                        @endif
                    </flux:menu>
                </flux:dropdown>
            </div>
        @endunless
    </div>

    @if ($this->directory && ! $item->trashed())
        <flux:card size="sm" class="flex flex-wrap items-center justify-between gap-3" data-test="inventory-checkout">
            @if ($item->checked_out_to)
                <div class="flex items-center gap-3">
                    <flux:badge color="sky" icon="arrow-right-start-on-rectangle">{{ __('Checked out') }}</flux:badge>
                    <flux:text>
                        {{ __('to :name', ['name' => $this->borrower?->name ?? __('someone no longer in the directory')]) }}
                        · {{ $item->checked_out_at?->diffForHumans() }}
                    </flux:text>
                </div>
                @if ($this->canManage)
                    <flux:button wire:click="checkIn" size="sm" data-test="inventory-check-in">{{ __('Check in') }}</flux:button>
                @endif
            @else
                <div class="flex items-center gap-3">
                    <flux:badge color="green" icon="check">{{ __('Available') }}</flux:badge>
                </div>
                @if ($this->canManage)
                    <flux:modal.trigger name="check-out">
                        <flux:button size="sm" data-test="inventory-check-out">{{ __('Check out') }}</flux:button>
                    </flux:modal.trigger>
                @endif
            @endif
        </flux:card>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div @class(['space-y-6', 'lg:col-span-2' => $item->metadata, 'lg:col-span-3' => ! $item->metadata])>
            @if ($item->photo_url)
                <img src="{{ $item->photo_url }}" alt="{{ $item->name }}" class="max-h-96 w-full rounded-lg object-cover" />
            @endif

            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">{{ __('Contents') }}</flux:heading>
                    @if ($this->children->total() > 0)
                        <flux:text>{{ trans_choice(':count item|:count items', $this->children->total()) }}</flux:text>
                    @endif
                </div>

                @if ($this->children->isEmpty())
                    <flux:card class="text-center">
                        <flux:text>{{ __('Nothing in here yet.') }}</flux:text>
                        @if ($this->canManage && ! $item->trashed())
                            <flux:button wire:click="create" icon="plus" variant="ghost" size="sm" class="mt-2">{{ __('Add an item here') }}</flux:button>
                        @endif
                    </flux:card>
                @else
                    <flux:table :paginate="$this->children">
                        <flux:table.columns>
                            <flux:table.column>{{ __('Name') }}</flux:table.column>
                            <flux:table.column>{{ __('Modified') }}</flux:table.column>
                            <flux:table.column>{{ __('Contents') }}</flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach ($this->children as $child)
                                <flux:table.row :key="$child->id" data-test="inventory-child-row">
                                    <flux:table.cell>
                                        <flux:link :href="route('inventory.show', ['current_team' => $this->team, 'item' => $child])" wire:navigate class="!flex !items-center gap-3">
                                            <flux:avatar size="xs" :src="$child->thumb_url" :icon="$child->type->icon()" :color="$child->type->color()" icon:variant="outline" />
                                            <span>{{ $child->truncated_name }}</span>
                                        </flux:link>
                                    </flux:table.cell>
                                    <flux:table.cell>{{ $child->updated_at->diffForHumans() }}</flux:table.cell>
                                    <flux:table.cell>{{ $child->children_count }}</flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @endif
            </div>
        </div>

        @if ($item->metadata)
            <div>
                <flux:card>
                    <flux:heading size="lg" class="mb-2">{{ __('Details') }}</flux:heading>

                    <dl data-test="inventory-metadata">
                        @foreach ($item->metadata as $key => $value)
                            <dt class="mt-3 mb-1 text-sm text-zinc-500 dark:text-white/70">{{ ucfirst($key) }}</dt>
                            <dd class="text-sm font-medium text-zinc-800 dark:text-white">{{ $value }}</dd>
                        @endforeach
                    </dl>
                </flux:card>
            </div>
        @endif
    </div>

    @include('inventory::modals.item-form')
    @include('inventory::modals.qr-code')
    @include('inventory::modals.move-item')
    @include('inventory::modals.duplicate-item')

    @if ($this->directory)
        @include('inventory::modals.check-out')
    @endif
</div>
