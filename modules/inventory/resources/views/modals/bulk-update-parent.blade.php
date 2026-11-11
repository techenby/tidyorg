<flux:modal name="bulk-update-parent" class="md:w-96">
    <form wire:submit="updateParent" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Change parent') }}</flux:heading>
            <flux:text class="mt-2">{{ trans_choice('Move :count item to a new parent.|Move :count items to a new parent.', count($selected)) }}</flux:text>
        </div>

        <flux:select wire:model="bulkParentId" :label="__('Parent')">
            <flux:select.option value="">{{ __('None (top level)') }}</flux:select.option>
            @foreach ($this->bulkParentOptions as $parentItem)
                <flux:select.option :value="$parentItem->id">{{ $parentItem->truncated_name }}</flux:select.option>
            @endforeach
        </flux:select>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary">{{ __('Update') }}</flux:button>
        </div>
    </form>
</flux:modal>
