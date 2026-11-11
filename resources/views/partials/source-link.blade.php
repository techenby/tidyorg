@if (config('tidyorg.source.url'))
    <p class="text-center text-xs text-zinc-500 dark:text-zinc-400" data-test="source-link">
        {{ __('Tidy Org is free software under the AGPL.') }}
        <a href="{{ config('tidyorg.source.url') }}" target="_blank" rel="noopener" class="underline">{{ __('Source code') }}</a>@if (config('tidyorg.source.revision')), {{ __('revision :revision', ['revision' => \Illuminate\Support\Str::limit(config('tidyorg.source.revision'), 12, '')]) }}@endif
    </p>
@endif
