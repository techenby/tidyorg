<flux:modal name="qr-code" variant="flyout" class="md:w-96">
    <div class="space-y-6" data-test="inventory-qr-code">
        <div class="space-y-1">
            <flux:heading size="lg">{{ __('QR code') }}</flux:heading>

            <flux:text>{{ $qrCode['name'] ?? '' }}</flux:text>
            <flux:link :href="$qrCode['url'] ?? ''" class="break-all">{{ $qrCode['url'] ?? '' }}</flux:link>
        </div>

        <div class="flex justify-center rounded-lg bg-white p-4">
            {!! $qrCode['svg'] ?? '' !!}
        </div>
    </div>
</flux:modal>
