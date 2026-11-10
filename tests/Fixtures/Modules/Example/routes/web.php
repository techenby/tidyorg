<?php

use Illuminate\Support\Facades\Route;

Route::livewire('/', 'example::index')->name('index');
Route::get('manage', fn () => 'Example admin')->name('manage');
