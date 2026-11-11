<flux:modal name="check-out" class="md:w-96">
    <form wire:submit="checkOut" class="space-y-6" data-test="inventory-check-out-form">
        <div>
            <flux:heading size="lg">{{ __('Check out') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Record who has this item.') }}</flux:text>
        </div>

        <flux:select wire:model="checkOutTo" :label="__('Person')" :placeholder="__('Select a person...')">
            @foreach ($this->people as $person)
                <flux:select.option :value="$person->id">{{ $person->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary">{{ __('Check out') }}</flux:button>
        </div>
    </form>
</flux:modal>
