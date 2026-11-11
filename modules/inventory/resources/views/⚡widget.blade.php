<?php

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Tessera\Inventory\Models\Item;

new class extends Component {
    public const LIMIT = 5;

    /**
     * The organization's most recently added items, or none when the user may not see its inventory.
     *
     * @return Collection<int, Item>
     */
    #[Computed]
    public function items(): Collection
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        if (! $team || ! $user->hasTeamPermission($team, 'inventory:view')) {
            return new Collection;
        }

        return Item::forTeam($team)
            ->with('parent')
            ->latest()
            ->latest('id')
            ->limit(self::LIMIT)
            ->get();
    }
}; ?>

<flux:card class="space-y-4" data-test="inventory-widget">
    <div class="flex items-center justify-between">
        <flux:heading size="lg">{{ __('Recently added items') }}</flux:heading>
        <flux:link :href="route('inventory.index', ['current_team' => auth()->user()->currentTeam])" variant="subtle" class="text-sm" wire:navigate>{{ __('Inventory') }}</flux:link>
    </div>

    @if ($this->items->isEmpty())
        <div class="py-4 text-center">
            <flux:icon name="archive-box" class="mx-auto mb-3 size-10 text-zinc-400" />
            <flux:text>{{ __('Keep track of what you own and where it is kept.') }}</flux:text>
            <flux:button size="sm" class="mt-4" icon="plus" :href="route('inventory.index', ['current_team' => auth()->user()->currentTeam])" wire:navigate>
                {{ __('Start your inventory') }}
            </flux:button>
        </div>
    @else
        <ul class="divide-y divide-zinc-900/5 dark:divide-white/10">
            @foreach ($this->items as $item)
                <li wire:key="widget-item-{{ $item->id }}">
                    <a href="{{ route('inventory.show', ['current_team' => auth()->user()->currentTeam, 'item' => $item]) }}" class="flex items-center gap-3 py-2" wire:navigate>
                        <flux:avatar size="sm" :src="$item->thumb_url" :icon="$item->type->icon()" :color="$item->type->color()" icon:variant="outline" />
                        <span class="min-w-0 flex-1">
                            <flux:heading class="truncate">{{ $item->truncated_name }}</flux:heading>
                            @if ($item->parent)
                                <flux:text size="sm" class="truncate">{{ __('in :parent', ['parent' => $item->parent->truncated_name]) }}</flux:text>
                            @endif
                        </span>
                        <flux:text size="sm" class="shrink-0">{{ $item->created_at->diffForHumans(short: true) }}</flux:text>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</flux:card>
