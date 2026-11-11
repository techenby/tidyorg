@php
    $modules = app(\App\Modules\ModuleRegistry::class)->active();
    $canRegister = Route::has('register') && ! \App\Models\TeamInvitation::required();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => __('Software for nonprofits')])
    </head>
    <body class="min-h-screen bg-white text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        <div class="mx-auto flex min-h-svh w-full max-w-5xl flex-col px-6">
            <header class="flex items-center justify-between gap-4 py-6">
                <a href="{{ route('home') }}" class="flex items-center gap-2 font-semibold">
                    <x-app-logo-icon class="size-7 fill-current" />
                    <span>{{ config('app.name') }}</span>
                </a>

                <nav class="flex items-center gap-2" aria-label="{{ __('Account') }}">
                    @auth
                        <flux:button :href="auth()->user()->homeUrl()" variant="primary" size="sm" data-test="welcome-open">
                            {{ __('Open :app', ['app' => config('app.name')]) }}
                        </flux:button>
                    @else
                        <flux:button :href="route('login')" variant="ghost" size="sm" data-test="welcome-login">
                            {{ __('Log in') }}
                        </flux:button>

                        @if ($canRegister)
                            <flux:button :href="route('register')" variant="primary" size="sm" data-test="welcome-register">
                                {{ __('Get started') }}
                            </flux:button>
                        @endif
                    @endauth
                </nav>
            </header>

            <main class="flex flex-1 flex-col gap-20 py-12 sm:py-20">
                <section class="max-w-2xl">
                    <h1 class="text-4xl font-semibold tracking-tight text-balance sm:text-5xl">
                        {{ __('The day-to-day of your nonprofit, in one tidy place.') }}
                    </h1>
                    <p class="mt-6 text-lg text-zinc-600 text-pretty dark:text-zinc-400">
                        {{ __(':app is open-source software for small nonprofits. Turn on the parts your organization uses and leave the rest off.', ['app' => config('app.name')]) }}
                    </p>

                    <div class="mt-8 flex flex-wrap items-center gap-3">
                        @auth
                            <flux:button :href="auth()->user()->homeUrl()" variant="primary">
                                {{ __('Open :app', ['app' => config('app.name')]) }}
                            </flux:button>
                        @else
                            @if ($canRegister)
                                <flux:button :href="route('register')" variant="primary">
                                    {{ __('Set up your organization') }}
                                </flux:button>
                            @endif

                            <flux:button :href="route('login')" :variant="$canRegister ? 'ghost' : 'primary'">
                                {{ __('Log in') }}
                            </flux:button>
                        @endauth
                    </div>
                </section>

                @if ($modules->isNotEmpty())
                    <section aria-labelledby="welcome-modules" data-test="welcome-modules">
                        <h2 id="welcome-modules" class="text-sm font-medium text-zinc-500 dark:text-zinc-400">
                            {{ __('What is included') }}
                        </h2>

                        <ul class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($modules as $module)
                                <li class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-800">
                                    <flux:icon :name="$module->icon()" class="size-6 text-zinc-500 dark:text-zinc-400" />
                                    <h3 class="mt-4 font-medium">{{ __($module->name()) }}</h3>
                                    @if ($module->description() !== '')
                                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ __($module->description()) }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                <section aria-labelledby="welcome-principles">
                    <h2 id="welcome-principles" class="text-sm font-medium text-zinc-500 dark:text-zinc-400">
                        {{ __('How it works') }}
                    </h2>

                    <dl class="mt-4 grid gap-8 sm:grid-cols-3">
                        <div>
                            <dt class="font-medium">{{ __('Only what you use') }}</dt>
                            <dd class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                                {{ __('Each part is a module. Your organization switches on the ones it needs, and the rest stay out of the way.') }}
                            </dd>
                        </div>
                        <div>
                            <dt class="font-medium">{{ __('Your data stays yours') }}</dt>
                            <dd class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                                {{ __('An owner can download a full copy of the organization\'s records and files at any time.') }}
                            </dd>
                        </div>
                        <div>
                            <dt class="font-medium">{{ __('Open source') }}</dt>
                            <dd class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                                {{ __('The code is free software under the AGPL. Anyone can read it, change it, or run it on their own server.') }}
                            </dd>
                        </div>
                    </dl>
                </section>

                @foreach (config('tidyorg.welcome_sections', []) as $section)
                    @include($section)
                @endforeach
            </main>

            <footer class="border-t border-zinc-200 py-8 dark:border-zinc-800">
                @include('partials.source-link')
            </footer>
        </div>

        @fluxScripts
    </body>
</html>
