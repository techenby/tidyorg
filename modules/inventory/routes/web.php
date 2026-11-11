<?php

use Illuminate\Support\Facades\Route;

Route::livewire('/', 'inventory::index')->name('index');

Route::livewire('{item}', 'inventory::show')
    ->whereNumber('item')
    ->withTrashed()
    ->name('show');
