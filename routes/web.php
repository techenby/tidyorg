<?php

use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    if (! config('tidyorg.single_organization')) {
        return view('welcome');
    }

    return redirect()->to($request->user()?->homeUrl() ?? route('login'));
})->name('home');

Route::livewire('onboarding', 'pages::onboarding')
    ->middleware(['auth', 'verified'])
    ->name('onboarding');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::view('dashboard', 'dashboard')->name('dashboard');
    });

require __DIR__.'/settings.php';
