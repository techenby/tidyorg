<flux:modal name="item-form" variant="flyout" class="md:w-96">
    <form wire:submit="save" class="space-y-6" data-test="inventory-item-form">
        <flux:heading size="lg">{{ $form->editingItem ? __('Edit item') : __('Add item') }}</flux:heading>

        <flux:input wire:model="form.name" :label="__('Name')" type="text" required data-test="inventory-item-name" />

        <flux:field>
            <flux:label>{{ __('Photo') }}</flux:label>

            @if ($form->photo && $form->photo->isPreviewable())
                <img src="{{ $form->photo->temporaryUrl() }}" alt="" class="max-h-40 rounded-lg object-cover" />
            @elseif ($form->existingPhotoUrl)
                <img src="{{ $form->existingPhotoUrl }}" alt="" class="max-h-40 rounded-lg object-cover" />
            @endif

            <div class="flex items-center gap-2">
                <x-file-input wire:model="form.photo" accept="image/*" data-test="inventory-item-photo" />

                @if ($form->photo || $form->existingPhotoUrl)
                    <flux:button wire:click="removePhoto" variant="ghost" size="sm" icon="x-mark" :aria-label="__('Remove photo')" />
                @endif
            </div>

            <flux:description>{{ __('JPG or PNG, up to 5 MB.') }}</flux:description>
            <flux:error name="form.photo" />
        </flux:field>

        <flux:select wire:model="form.type" :label="__('Type')">
            @foreach (\Tessera\Inventory\Enums\ItemType::cases() as $type)
                <flux:select.option :value="$type->value">{{ __($type->label()) }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model="form.parent_id" :label="__('Parent')">
            <flux:select.option value="">{{ __('None (top level)') }}</flux:select.option>
            @foreach ($this->parentItems as $parentItem)
                <flux:select.option :value="$parentItem->id">{{ $parentItem->truncated_name }}</flux:select.option>
            @endforeach
        </flux:select>

        <div>
            <flux:label class="mb-2">{{ __('Details') }}</flux:label>

            <div class="space-y-2">
                @foreach ($form->metadata as $index => $pair)
                    <div class="flex items-start gap-2" wire:key="metadata-{{ $index }}">
                        <flux:input wire:model="form.metadata.{{ $index }}.key" :placeholder="__('Label')" :aria-label="__('Label')" size="sm" />
                        <flux:input wire:model="form.metadata.{{ $index }}.value" :placeholder="__('Value')" :aria-label="__('Value')" size="sm" />
                        <flux:button variant="ghost" size="sm" icon="x-mark" wire:click="removeMetadata({{ $index }})" :aria-label="__('Remove')" />
                    </div>
                @endforeach
            </div>

            <flux:error name="form.metadata.*.key" />
            <flux:error name="form.metadata.*.value" />

            <flux:button variant="ghost" size="sm" icon="plus" wire:click="addMetadata" class="mt-2" data-test="inventory-add-detail">{{ __('Add detail') }}</flux:button>
        </div>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary" data-test="inventory-item-save">{{ $form->editingItem ? __('Update') : __('Create') }}</flux:button>
        </div>
    </form>
</flux:modal>
