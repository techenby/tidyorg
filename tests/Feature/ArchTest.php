<?php

use App\Modules\Module;
use Composer\InstalledVersions;

arch('core never depends on a module')
    ->expect('App')
    ->not->toUse('Tessera');

/*
 * Every module in modules/ is read from its composer.json, so a new module is
 * covered without touching this file. A module that is not installed in this
 * checkout is skipped.
 */
$modules = collect(glob(__DIR__.'/../../modules/*/composer.json') ?: [])
    ->map(fn (string $file): array => json_decode((string) file_get_contents($file), true))
    ->filter(fn (array $composer): bool => InstalledVersions::isInstalled($composer['name']))
    ->mapWithKeys(fn (array $composer): array => [$composer['name'] => [
        'namespace' => rtrim((string) array_search('src/', $composer['autoload']['psr-4'], true), '\\'),
        'requires' => array_keys($composer['require'] ?? []),
    ]]);

foreach ($modules as $name => $module) {
    arch("{$name} uses core only through its public surface")
        ->expect($module['namespace'])
        ->not->toUse([
            'App\Actions',
            'App\Console',
            'App\Data',
            'App\Http',
            'App\Livewire',
            'App\Notifications',
            'App\Policies',
            'App\Providers',
            'App\Rules',
        ]);

    /*
     * An organization's data must be exportable and deletable in full, so
     * every module says what it stores, even when the answer is nothing.
     */
    test("{$name} says how its data is exported and purged", function () use ($module) {
        $provider = collect(get_declared_classes())
            ->first(fn (string $class): bool => str_starts_with($class, $module['namespace'].'\\') && is_subclass_of($class, Module::class));

        expect($provider)->not->toBeNull();

        foreach (['exporting', 'purging'] as $hook) {
            expect((new ReflectionMethod($provider, $hook))->getDeclaringClass()->getName())->toBe($provider);
        }
    });

    $undeclared = $modules
        ->reject(fn (array $other, string $otherName): bool => $otherName === $name || in_array($otherName, $module['requires'], true))
        ->pluck('namespace')
        ->all();

    if ($undeclared !== []) {
        arch("{$name} uses no module it does not require")
            ->expect($module['namespace'])
            ->not->toUse($undeclared);
    }
}
