<flux:modal name="move-item" class="md:w-96">
    <form wire:submit="moveToTeam" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Move to organization') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Hand this item to another organization you manage inventory for. Anything inside it stays here.') }}</flux:text>
        </div>

        <flux:select wire:model="moveToTeamId" :label="__('Organization')" :placeholder="__('Select an organization...')">
            @foreach ($this->otherTeams as $otherTeam)
                <flux:select.option :value="$otherTeam->id">{{ $otherTeam->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary">{{ __('Move') }}</flux:button>
        </div>
    </form>
</flux:modal>
