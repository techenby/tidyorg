<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use App\Modules\Facades\Modules;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

test('an installed module that is not active registers no routes, navigation, or tables', function () {
    installModules(active: '');

    $user = User::factory()->create();
    config(['tidyorg.single_organization' => true]);

    expect(Modules::isInstalled('example'))->toBeTrue()
        ->and(Modules::isActive('example'))->toBeFalse()
        ->and(Route::has('example.index'))->toBeFalse()
        ->and(Schema::hasTable('example_things'))->toBeFalse()
        ->and(Modules::navigationFor($user->currentTeam, $user))->toBeEmpty();
});

test('only the modules listed in configuration are active', function () {
    installModules(active: 'example, something-else');

    expect(Modules::active()->keys()->all())->toBe(['example']);
});

test('an active module that is not enabled for the organization is hidden and returns 404', function () {
    installModules();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('example.index', ['current_team' => $user->currentTeam->slug]))
        ->assertNotFound();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Example things')
        ->assertDontSeeHtml('data-test="example-widget"');
});

test('an enabled module serves its pages, navigation, and dashboard widgets', function () {
    installModules();

    $user = User::factory()->create();
    $user->currentTeam->modules()->create(['module' => 'example']);

    $this->actingAs($user)
        ->get(route('example.index', ['current_team' => $user->currentTeam->slug]))
        ->assertOk()
        ->assertSeeHtml('data-test="example-index"');

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Example things')
        ->assertSeeHtml('data-test="example-widget"');
});

test('a module enabled for one organization is not available in another', function () {
    installModules();

    $user = User::factory()->create();
    $enabledTeam = $user->currentTeam;
    $otherTeam = Team::factory()->create();
    $otherTeam->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $enabledTeam->modules()->create(['module' => 'example']);

    $this->actingAs($user)
        ->get(route('example.index', ['current_team' => $otherTeam->slug]))
        ->assertNotFound();
});

test('module pages cannot be reached by people outside the organization', function () {
    installModules();

    $owner = User::factory()->create();
    $owner->currentTeam->modules()->create(['module' => 'example']);

    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->get(route('example.index', ['current_team' => $owner->currentTeam->slug]))
        ->assertForbidden();
});

test('guests are redirected to the login page from module pages', function () {
    installModules();

    $team = Team::factory()->create();

    $this->get(route('example.index', ['current_team' => $team->slug]))
        ->assertRedirect(route('login'));
});

test('single organization mode enables every active module without per-organization records', function () {
    installModules();
    config(['tidyorg.single_organization' => true]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('example.index', ['current_team' => $user->currentTeam->slug]))
        ->assertOk();
});
