<?php

namespace App\Modules;

/**
 * An entry a module adds to the main navigation.
 */
readonly class NavigationItem
{
    /**
     * @param  string  $label  The untranslated label; it is passed through __() when rendered.
     * @param  string  $route  The name of the route the entry links to.
     * @param  string|null  $permission  The module permission required to see the entry, if any.
     */
    public function __construct(
        public string $label,
        public string $route,
        public string $icon = 'puzzle-piece',
        public ?string $permission = null,
    ) {
        //
    }

    /**
     * The route name pattern that marks the entry as current.
     */
    public function currentPattern(): string
    {
        return str($this->route)->beforeLast('.')->append('.*')->toString();
    }
}
