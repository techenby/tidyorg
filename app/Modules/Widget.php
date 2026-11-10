<?php

namespace App\Modules;

/**
 * A Livewire component a module adds to the organization dashboard.
 */
readonly class Widget
{
    /**
     * @param  string  $component  The Livewire component name, such as "inventory::widgets.recent-items".
     * @param  string|null  $permission  The module permission required to see the widget, if any.
     */
    public function __construct(
        public string $component,
        public ?string $permission = null,
    ) {
        //
    }
}
