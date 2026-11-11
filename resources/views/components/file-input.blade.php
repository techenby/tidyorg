{{-- A native file picker styled to sit beside Flux controls. Pass wire:model and accept as usual. --}}
<input
    type="file"
    {{ $attributes->class([
        'block w-full min-w-0 text-sm text-zinc-500 dark:text-zinc-400',
        'file:me-3 file:cursor-pointer file:rounded-lg file:border file:border-solid file:border-zinc-200 file:bg-white file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-zinc-800 hover:file:bg-zinc-50',
        'dark:file:border-white/10 dark:file:bg-zinc-700 dark:file:text-white dark:hover:file:bg-zinc-600',
    ]) }}
/>
