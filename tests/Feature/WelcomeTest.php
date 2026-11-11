<?php

use App\Models\User;
use Illuminate\Support\Facades\View;

test('guests are offered the way in and shown the modules that are switched on', function () {
    config(['tidyorg.modules' => 'inventory']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('href="'.route('login').'"', false)
        ->assertSee('href="'.route('register').'"', false)
        ->assertSee('Track what your organization owns and where it is kept.')
        ->assertDontSee('Keep a directory of the people your organization works with.');
});

test('the module list is left out when no module is switched on', function () {
    config(['tidyorg.modules' => '']);

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('data-test="welcome-modules"', false);
});

test('someone who is signed in is sent to their organization instead of the sign-in pages', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertSee('href="'.$user->homeUrl().'"', false)
        ->assertDontSee('href="'.route('register').'"', false)
        ->assertDontSee('data-test="welcome-login"', false);
});

test('a package can add sections to the end of the page', function () {
    View::addNamespace('welcome-test', base_path('tests/Fixtures/views'));
    config(['tidyorg.welcome_sections' => ['welcome-test::section']]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('A section added by a package');
});

test('the page links to the source code when an address is set', function () {
    config(['tidyorg.source' => ['url' => 'https://example.org/tidyorg', 'revision' => null]]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('href="https://example.org/tidyorg"', false);
});
