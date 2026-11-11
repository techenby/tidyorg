<flux:modal name="duplicate-item" class="md:w-96">
    <form wire:submit="duplicate" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Duplicate item') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Create one or more copies of this item.') }}</flux:text>
        </div>

        <flux:input type="number" min="1" max="25" wire:model="duplicateCount" :label="__('Number of copies')" />

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary">{{ __('Duplicate') }}</flux:button>
        </div>
    </form>
</flux:modal>
