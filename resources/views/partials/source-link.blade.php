@if (config('tessera.source.url'))
    <p class="text-center text-xs text-zinc-500 dark:text-zinc-400" data-test="source-link">
        {{ __('Tessera is free software under the AGPL.') }}
        <a href="{{ config('tessera.source.url') }}" target="_blank" rel="noopener" class="underline">{{ __('Source code') }}</a>@if (config('tessera.source.revision')), {{ __('revision :revision', ['revision' => \Illuminate\Support\Str::limit(config('tessera.source.revision'), 12, '')]) }}@endif
    </p>
@endif
